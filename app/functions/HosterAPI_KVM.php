<?php

$venocix = new HosterAPI_KVM();
class HosterAPI_KVM extends Controller
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

#only required for redhost
    public function getTraffic($ip, $from_date = 0, $to_date = 0)
    {
//        $url = "https://api.venocix.de/temp/traffic/".env('VENOCIX_API_TOKEN')."/".$ip."/".$from_date."/".$to_date;
//        $response = self::getClient()->get($url)->getBody();
//        return json_decode($response);
    }

    public function setRDNS($vm_id, $ip, $rdns)
    {

        $client = new \GuzzleHttp\Client();
        $response = $client->post([
            ''. env('VENOCIX_API_URL').'/datacenter/server/' . $vm_id . '/rdns',

            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],

                'json' => [
                    'ip' => $ip,
                    'hostname' => $rdns,
                ],
            ]
        ]);

        return json_decode((string)$response->getBody());
    }

    public function getTemplates()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/templates',
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

    public function getIncident($vm_id)
    {

        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/incidents',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server',
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
            ''. env('VENOCIX_API_URL').'/job/'.$job_id,
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

    public function currentVMStatus($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/status',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/delete',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/start',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/shutdown',
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

    public function hardstop($vm_id) {

        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'stop',
            [
                'headers' => [
                    'Content-Type', 'application/json',
                    'Accept', 'application/json',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/reboot',
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
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/password/reset',
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

    public function reinstallVM($vm_id, $serverOS="Debian10.0")
    {

        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/reinstall',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'template' => $serverOS,
                ],
            ]
        );
        return json_decode((string) $response->getBody());
    }

    public function getVNC($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/console',

            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
            ]
        );

        $resp = json_decode((string) $response->getBody());

        return $resp->result;
    }

    public function getBackupList($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/backups/list',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
            ]
        );

        $resp = $response->getBody();

        return json_decode((string) $resp);

    }

    public function createBackup($vm_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->put(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/backups/create',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
            ]
        );
    }

    public function getBackupStatus($vm_id)
    {

        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/backups/status',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
            ]
        );

        $resp = $response->getBody();

        return json_decode((string) $resp);

    }

    public function restoreBackup($vm_id, $backup_id)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(
            ''. env('VENOCIX_API_URL').'/datacenter/server/'.$vm_id.'/backups/restore',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'backup' => $backup_id,
                ],
            ]
        );
    }

    public function getSoftware()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(
            ''. env('VENOCIX_API_URL').'/software/list',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
            ]
        );
        $body = $response->getBody();
        return(json_decode((string) $body));
    }

    public function installSoftware($vm_id, $password, $package)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(
            ''. env('VENOCIX_API_URL').'/software/install',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'sid' => $vm_id,
                    'package' => $package,
                    'password' => $password,
                    'port' => 22,
                ],
            ]
        );
        $body = $response->getBody();
        return (json_decode((string) $body));
    }


    public function uninstallSoftware($vm_id, $password, $package)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(
            ''. env('VENOCIX_API_URL').'/software/uninstall',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('VENOCIX_API_KEY'),
                ],
                'json' => [
                    'sid' => $vm_id,
                    'package' => $package,
                    'password' => $password,
                    'port' => 22,
                ],
            ]
        );
        $body = $response->getBody();
        return (json_decode((string) $body));
    }

}