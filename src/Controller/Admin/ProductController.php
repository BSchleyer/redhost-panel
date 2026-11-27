<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Billing\Money;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Catalog\ProductCategory;
use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Network\IpPoolService;
use Nebrix\Repository\CustomerGroupRepository;
use Nebrix\Repository\ProductRepository;
use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;
use Nebrix\Settings\SettingsRepository;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CustomerGroupRepository $groups,
        private readonly SettingsRepository $settings,
        private readonly Connection $db,
        private readonly IpPoolService $ipPools,
    ) {
    }

    public function index(Request $request): Response
    {
        $category = $request->string('category');
        $filters = [
            'category' => ProductCategory::exists($category) ? $category : '',
            'search' => mb_substr($request->string('search'), 0, 100),
            'active' => in_array($request->string('active'), ['0', '1'], true) ? $request->string('active') : '',
        ];

        return $this->render($request, 'admin/products/index.twig', [
            'products' => $this->products->all($filters),
            'filters' => $filters,
            'categories' => ProductCategory::labels(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize($request, Permission::PRODUCTS_MANAGE);
        $category = $request->string('category');
        if (!ProductCategory::exists($category)) {
            return $this->render($request, 'admin/products/choose_category.twig', ['categories' => ProductCategory::all()]);
        }

        $definition = ProductCategory::get($category);
        $defaults = [];
        foreach ($definition['config'] as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $this->form($request, null, $category, $defaults);
    }

    public function store(Request $request): Response
    {
        $this->authorize($request, Permission::PRODUCTS_MANAGE);
        $category = $request->string('category');
        if (!ProductCategory::exists($category)) {
            throw HttpException::badRequest();
        }

        $id = $this->products->create($this->validated($request, $category, 0) + ['category' => $category]);
        $this->audit($request, 'product.created', 'product', $id);
        $this->flash($request, 'success', 'Product created.');

        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    public function edit(Request $request, int $id): Response
    {
        $product = $this->find($id);

        return $this->form($request, $product, (string) $product['category'], $product['provisioning_config']);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::PRODUCTS_MANAGE);
        $product = $this->find($id);
        $data = $this->validated($request, (string) $product['category'], $id);
        $this->products->update($id, $data);
        // Price changes do not alter existing services; they keep the price agreed at order time.
        $this->audit($request, 'product.updated', 'product', $id, ['price' => $data['price'], 'active' => $data['is_active']]);
        $this->flash($request, 'success', 'Product saved. Existing services keep their current price.');

        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /**
     * @param array<string, mixed>|null $product
     * @param array<string, mixed> $config
     */
    private function form(Request $request, ?array $product, string $category, array $config): Response
    {
        $definition = $this->withDynamicOptions(ProductCategory::get($category));
        $integrations = [];
        if ($definition['integration'] !== null) {
            foreach ($this->db->fetchAll('SELECT `id`, `name`, `is_enabled` FROM `nx_integrations` WHERE `type` = ? ORDER BY `name`', [$definition['integration']]) as $row) {
                $integrations[(int) $row['id']] = $row['name'] . ((int) $row['is_enabled'] === 1 ? '' : ' (disabled)');
            }
        }

        $currency = $product['currency'] ?? ($this->settings->string('general.default_currency') ?: 'EUR');

        return $this->render($request, 'admin/products/form.twig', [
            'product' => $product,
            'category' => $category,
            'definition' => $definition,
            'config' => $config,
            'integrations' => $integrations,
            'groups' => $this->groups->options(),
            'cycles' => BillingCycle::labels(),
            'currencies' => array_combine($this->settings->list('billing.supported_currencies'), $this->settings->list('billing.supported_currencies')) ?: ['EUR' => 'EUR'],
            'price' => $product !== null ? Money::toDecimal((int) $product['price'], (string) $currency) : '',
            'setup_fee' => $product !== null ? Money::toDecimal((int) $product['setup_fee'], (string) $currency) : '0.00',
            'tax_rate' => $product !== null ? number_format((int) $product['tax_rate_bp'] / 100, 2, '.', '') : '0.00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $category, int $id): array
    {
        $currencies = implode(',', $this->settings->list('billing.supported_currencies') ?: ['EUR']);
        $data = $this->validate($request, [
            'name' => 'required|string|max:150',
            'slug' => 'required|slug|max:160',
            'description' => 'nullable|string|max:5000',
            'features' => 'nullable|string|max:5000',
            'price' => 'required|decimal|min:0',
            'setup_fee' => 'required|decimal|min:0',
            'currency' => 'required|in:' . $currencies,
            'billing_cycle' => 'required|in:' . implode(',', array_keys(BillingCycle::labels())),
            'tax_rate' => 'required|decimal|between:0,100',
            'stock' => 'nullable|int|min:0|max:1000000',
            'is_visible' => 'bool',
            'is_active' => 'bool',
            'auto_provision' => 'bool',
            'integration_id' => 'nullable|int|min:1',
            'customer_group_id' => 'nullable|int|min:1',
            'sort_order' => 'nullable|int|min:-1000|max:1000',
        ]);

        if ($this->products->slugExists((string) $data['slug'], $id)) {
            throw ValidationException::withMessages(['slug' => 'This slug is already used by another product.']);
        }

        $definition = ProductCategory::get($category);
        if ($data['integration_id'] !== null) {
            $type = $this->db->fetchValue('SELECT `type` FROM `nx_integrations` WHERE `id` = ?', [$data['integration_id']]);
            if ($type === null || $type !== $definition['integration']) {
                throw ValidationException::withMessages(['integration_id' => 'Choose an integration of the matching type.']);
            }
        }
        if ($data['auto_provision'] && $definition['integration'] !== null && $data['integration_id'] === null) {
            throw ValidationException::withMessages(['integration_id' => 'Automatic provisioning requires an integration.']);
        }
        if ($data['customer_group_id'] !== null && $this->groups->find((int) $data['customer_group_id']) === null) {
            throw ValidationException::withMessages(['customer_group_id' => 'The selected group does not exist.']);
        }

        // Category-specific provisioning configuration (fields prefixed with "config_").
        $configInput = [];
        $configRules = [];
        foreach ($definition['config'] as $key => $field) {
            $configInput[$key] = $request->input('config_' . $key);
            $configRules[$key] = $field['rules'];
        }
        try {
            $config = Validator::validate($configInput, $configRules, array_map(static fn (array $f): string => $f['label'], $definition['config']));
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['config_' . $key] = $messages;
            }
            throw new ValidationException($errors, Validator::safeInput($request->all()));
        }
        // Select fields only accept offered values (static or loaded from the database).
        foreach ($this->withDynamicOptions($definition)['config'] as $key => $field) {
            $value = $config[$key] ?? null;
            if (isset($field['options']) && $value !== null && $value !== '' && !array_key_exists((string) $value, $field['options'])) {
                throw new ValidationException(['config_' . $key => ['Choose one of the offered options.']], Validator::safeInput($request->all()));
            }
        }

        $currency = (string) $data['currency'];

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'features' => $data['features'],
            'price' => Money::parse((string) $data['price'], $currency),
            'setup_fee' => Money::parse((string) $data['setup_fee'], $currency),
            'currency' => $currency,
            'billing_cycle' => $data['billing_cycle'],
            'tax_rate_bp' => (int) round((float) $data['tax_rate'] * 100),
            'stock' => $data['stock'],
            'is_visible' => $data['is_visible'],
            'is_active' => $data['is_active'],
            'auto_provision' => $definition['integration'] !== null && $data['auto_provision'],
            'integration_id' => $data['integration_id'],
            'customer_group_id' => $data['customer_group_id'],
            'sort_order' => $data['sort_order'] ?? 0,
            'provisioning_config' => $config,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        return $this->products->find($id) ?? throw HttpException::notFound();
    }

    /**
     * Fills select options that come from the database (e.g. IP pools).
     *
     * @param array{label: string, integration: string|null, config: array<string, array<string, mixed>>, options: array<string, array<string, mixed>>} $definition
     * @return array{label: string, integration: string|null, config: array<string, array<string, mixed>>, options: array<string, array<string, mixed>>}
     */
    private function withDynamicOptions(array $definition): array
    {
        foreach ($definition['config'] as $key => $field) {
            if (($field['options_source'] ?? null) === 'ip_pools') {
                $definition['config'][$key]['options'] = $this->ipPools->options();
            }
        }

        return $definition;
    }
}
