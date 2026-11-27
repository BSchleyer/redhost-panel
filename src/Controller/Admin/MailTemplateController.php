<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Mail\MailTemplates;

final class MailTemplateController extends Controller
{
    public function __construct(private readonly MailTemplates $templates)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize($request, Permission::MAIL_TEMPLATES_MANAGE);
        $list = [];
        foreach (array_keys($this->templates->defaults()) as $key) {
            $list[] = $this->templates->get($key);
        }

        return $this->render($request, 'admin/mail_templates/index.twig', ['templates' => $list]);
    }

    public function edit(Request $request, string $key): Response
    {
        $this->authorize($request, Permission::MAIL_TEMPLATES_MANAGE);

        $locale = $this->locale($request);

        return $this->render($request, 'admin/mail_templates/edit.twig', [
            'template' => $this->find($key, $locale),
            'locales' => $this->templates->locales(),
            'locale' => $locale,
        ]);
    }

    private function locale(Request $request): string
    {
        $value = $request->input('locale');

        return $this->templates->normalizeLocale(is_string($value) ? $value : null);
    }

    public function update(Request $request, string $key): Response
    {
        $this->authorize($request, Permission::MAIL_TEMPLATES_MANAGE);
        $locale = $this->locale($request);
        $this->find($key, $locale);
        $data = $this->validate($request, [
            'subject' => 'required|string|max:255',
            'html' => 'required|string|max:100000',
        ]);

        $this->templates->save($key, (string) $data['subject'], (string) $data['html'], $this->user($request)->id, $locale);
        $this->audit($request, 'mail_template.updated', 'mail_template', $key, ['locale' => $locale]);
        $this->flash($request, 'success', 'Template saved.');

        return $this->redirect('/admin/mail-templates/' . $key . '?locale=' . $locale);
    }

    public function reset(Request $request, string $key): Response
    {
        $this->authorize($request, Permission::MAIL_TEMPLATES_MANAGE);
        $locale = $this->locale($request);
        $this->find($key, $locale);
        $this->templates->reset($key, $locale);
        $this->audit($request, 'mail_template.reset', 'mail_template', $key, ['locale' => $locale]);
        $this->flash($request, 'success', 'Template reset to the default version.');

        return $this->redirect('/admin/mail-templates/' . $key . '?locale=' . $locale);
    }

    /**
     * Renders the template with sample values. The response is sandboxed by
     * its own CSP (no scripts, no forms) and may only be framed by the panel.
     */
    public function preview(Request $request, string $key): Response
    {
        $this->authorize($request, Permission::MAIL_TEMPLATES_MANAGE);
        $locale = $this->locale($request);
        $template = $this->find($key, $locale);

        $sample = [];
        foreach ($template['variables'] as $variable) {
            $sample[$variable] = str_ends_with($variable, '_url') ? 'https://panel.example.com/example' : '[' . $variable . ']';
        }
        $rendered = $this->templates->render($key, $sample, $locale);

        $response = Response::html($rendered['html']);
        $response->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data: https:; frame-ancestors 'self'; sandbox");
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    /**
     * @return array{key: string, locale: string, name: string, variables: list<string>, subject: string, html: string, customized: bool}
     */
    private function find(string $key, ?string $locale = null): array
    {
        if (!array_key_exists($key, $this->templates->defaults())) {
            throw HttpException::notFound();
        }

        return $this->templates->get($key, $locale);
    }
}
