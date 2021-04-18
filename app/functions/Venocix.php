<?php

$venocix = new Venocix();
class Venocix extends Controller
{

    public function getClient() : \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'allow_redirects' => false,
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }


    public function getTraffic($ip, $from_date = 0, $to_date = 0)
    {
        $url = "https://api.venocix.de/temp/traffic/".env('VENOCIX_API_TOKEN')."/".$ip."/".$from_date."/".$to_date;
        $response = self::getClient()->get($url)->getBody();
        return json_decode($response);
    }

    public function setRDNS($ip, $rdns)
    {
        $url = "https://api.venocix.de/temp/rdns/".env('VENOCIX_API_TOKEN')."/".$ip."/".$rdns;
//        return $url;
        $response = self::getClient()->post($url)->getBody();
        return json_decode($response);
    }

    public function getTemplates()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            'https://reseller.venocix.de/api/v1/datacenter/templates',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function createVM($cores, $memory, $disk, $ip_count, $template)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(
            'https://reseller.venocix.de/api/v1/datacenter/server',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'template' => $template,
                    'cpuCores' => $cores,
                    'mem' => $memory,
                    'disk' => $disk.'G',
                    'ipCount' => $ip_count,
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function getJobInfo($job_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            'https://reseller.venocix.de/api/v1/job/'.$job_id,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
                'query' => [
                    'int'=> 'consectetur',
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function currentVMStatus($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/status',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function removeVM($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/delete',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'force' => true,
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function start($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/start',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function stop($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/shutdown',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function reboot($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/reboot',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function resetRootPW($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/password/reset',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function reinstallVM($vm_id, $server_os = 'Debian10.0')
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            'https://reseller.venocix.de/api/v1/datacenter/server/'.$vm_id.'/reinstall',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'template' => $server_os,
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

}