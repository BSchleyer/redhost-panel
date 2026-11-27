<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SimpleXMLElement;

/**
 * Stateful fake of the Plesk XML API (customer, webspace, server operations
 * used by Nebrix).
 */
final class FakePlesk
{
    /** @var array<int, array{login: string, external_id: string, password: string, name: string}> */
    public array $customers = [];

    /** @var array<int, array{name: string, owner_id: int, status: int, plan: string, ftp_login: string, ftp_password: string, ip: string}> */
    public array $webspaces = [];

    /** @var list<string> raw request bodies */
    public array $requests = [];

    private int $nextId = 10;

    public function __construct(private readonly string $key = 'plesk-secret-key')
    {
    }

    public function register(FakeHttp $http): void
    {
        $http->on('POST', '/enterprise/control/agent.php', function (RequestInterface $request): Response {
            $body = (string) $request->getBody();
            $this->requests[] = $body;
            if ($request->getHeaderLine('KEY') !== $this->key) {
                return $this->xml('<packet><system><status>error</status><errcode>1001</errcode><errtext>Authentication failed - wrong password.</errtext></system></packet>');
            }
            $packet = new SimpleXMLElement($body);
            foreach ($packet->children() as $object) {
                foreach ($object->children() as $operation) {
                    return $this->xml('<packet><' . $object->getName() . '><' . $operation->getName() . '>' . $this->handle($object->getName(), $operation->getName(), $operation) . '</' . $operation->getName() . '></' . $object->getName() . '></packet>');
                }
            }

            return $this->xml('<packet><system><status>error</status><errcode>1014</errcode><errtext>Parser error</errtext></system></packet>');
        });
    }

    private function handle(string $object, string $operation, SimpleXMLElement $request): string
    {
        $notFound = '<result><status>error</status><errcode>1013</errcode><errtext>Object not found.</errtext></result>';

        switch ($object . '.' . $operation) {
            case 'server.get':
                return '<result><status>ok</status><stat><version><plesk_version>18.0.62</plesk_version></version></stat></result>';
            case 'customer.get':
                foreach ($this->customers as $id => $customer) {
                    if ($customer['external_id'] === (string) $request->filter->{'external-id'}) {
                        return '<result><status>ok</status><id>' . $id . '</id></result>';
                    }
                }

                return $notFound;
            case 'customer.add':
                $info = $request->gen_info;
                $id = $this->nextId++;
                $this->customers[$id] = ['login' => (string) $info->login, 'external_id' => (string) $info->{'external-id'}, 'password' => (string) $info->passwd, 'name' => (string) $info->pname];

                return '<result><status>ok</status><id>' . $id . '</id></result>';
            case 'customer.set':
                $this->customers[(int) $request->filter->id]['password'] = (string) $request->values->gen_info->passwd;

                return '<result><status>ok</status><id>' . (int) $request->filter->id . '</id></result>';
            case 'webspace.get':
                foreach ($this->webspaces as $id => $webspace) {
                    if ($webspace['name'] === (string) $request->filter->name) {
                        return sprintf('<result><status>ok</status><id>%d</id><data><gen_info><name>%s</name><owner-id>%d</owner-id><status>%d</status></gen_info></data></result>', $id, htmlspecialchars($webspace['name'], ENT_XML1), $webspace['owner_id'], $webspace['status']);
                    }
                }

                return $notFound;
            case 'webspace.add':
                $properties = [];
                foreach ($request->hosting->vrt_hst->property as $property) {
                    $properties[(string) $property->name] = (string) $property->value;
                }
                $id = $this->nextId++;
                $this->webspaces[$id] = ['name' => (string) $request->gen_setup->name, 'owner_id' => (int) $request->gen_setup->{'owner-id'}, 'status' => 0, 'plan' => (string) $request->{'plan-name'}, 'ftp_login' => $properties['ftp_login'] ?? '', 'ftp_password' => $properties['ftp_password'] ?? '', 'ip' => (string) $request->gen_setup->ip_address];

                return '<result><status>ok</status><id>' . $id . '</id><guid>guid-' . $id . '</guid></result>';
            case 'webspace.set':
                $id = (int) $request->filter->id;
                if (!isset($this->webspaces[$id])) {
                    return $notFound;
                }
                $this->webspaces[$id]['status'] = (int) $request->values->gen_setup->status;

                return '<result><status>ok</status><id>' . $id . '</id></result>';
            case 'webspace.del':
                $id = (int) $request->filter->id;
                if (!isset($this->webspaces[$id])) {
                    return $notFound;
                }
                unset($this->webspaces[$id]);

                return '<result><status>ok</status><id>' . $id . '</id></result>';
            case 'server.create_session':
                return '<result><status>ok</status><id>sess' . md5((string) $request->login . base64_decode((string) $request->data->user_ip)) . '</id></result>';
        }

        return '<result><status>error</status><errcode>1014</errcode><errtext>Unsupported operation</errtext></result>';
    }

    private function xml(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'text/xml'], '<?xml version="1.0" encoding="UTF-8"?>' . $body);
    }
}
