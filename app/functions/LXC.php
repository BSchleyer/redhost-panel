<?php
use ProxmoxVE\Proxmox;
use phpseclib\Net\SSH2;

$lxc = new LXC();
class LXC extends Controller {

    public function getServerCredentials($nodeID){
        $getServerCredentials = self::db()->prepare("SELECT * FROM `vm_host_nodes` WHERE `id` = :nodeID");
        $getServerCredentials->execute(array(":nodeID" => $nodeID));
        $credentials = $getServerCredentials->fetch(PDO::FETCH_ASSOC);

        return $serverCredentials = [ 'hostname' => $credentials['hostname'], 'username' => $credentials['username'], 'password' => $credentials['password'], 'realm' => $credentials['realm'], ];
    }

    public function getNodeStats($nodeID){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->get('/nodes');

        return $response;

    }

    public function getPVE($nodeID){

        $getServerCredentials = self::db()->prepare("SELECT * FROM `vm_host_nodes` WHERE `id` = :nodeID");
        $getServerCredentials->execute(array(":nodeID" => $nodeID));
        $credentials = $getServerCredentials->fetch(PDO::FETCH_ASSOC);

        $nodeName = $credentials['name'];

        return '/nodes/'.$nodeName.'/';
    }

    public function startServer($nodeID, $serverID){

        $status = $this->getStatus($nodeID, $serverID);
        $status = json_decode($status);


        if($status->data->status == 'stopped'){
            $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
            $proxmoxVE->setResponseType('json');
            $response = $proxmoxVE->create($this->getPVE($nodeID).'lxc/'.$serverID.'/status/start');

            return $response;
        }

        return FALSE;

    }

    public function stopServer($nodeID, $serverID){

        $status = $this->getStatus($nodeID, $serverID);
        $status = json_decode($status);


        if($status->data->status == 'running'){
            $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
            $proxmoxVE->setResponseType('json');
            $response = $proxmoxVE->create($this->getPVE($nodeID).'lxc/'.$serverID.'/status/stop');

            return $response;
        }

        return FALSE;

    }

    public function shutdown($nodeID, $serverID){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->create($this->getPVE($nodeID).'lxc/'.$serverID.'/status/shutdown');

        return $response;

    }

    public function create($nodeID, $serverID, $ostemplate, $cores, $memory, $password, $disc, $swap, array $ip, $addr_count, $hostname, $storage, $network_rate = 60){

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $arr = [
            'ostemplate' => 'local:vztmpl/'.$ostemplate,
            'vmid' => $serverID,
            'cores' => $cores,
            'cpulimit' => $cores,
            'cpuunits' => 1024,
            'hostname' => $hostname,
            'memory' => $memory,
            'password' => $password,
            'rootfs' => $disc,
            'swap' => $swap,
            'storage' => $storage,
            'unprivileged' => true,
        ];
        $i = 0;
        foreach ($ip as $key => $address) {

            if($addr_count >= $i+1){
                $arr['net' . $i] = 'name=eth' . $i . ',ip=' . $address->ip . '/' . $address->cidr . ',gw=' . $address->gateway . ',bridge=vmbr0,rate='.$network_rate.',hwaddr=' . $address->mac_address;

                $update = self::db()->prepare("UPDATE `ip_addresses` SET `service_id`=:service_id,`service_type`=:service_type WHERE `id`=:id");
                $update->execute(array(":service_id" => $serverID, ":service_type" => 'VPS', ":id" => $address->id));

                $i++;
            }

        }


        $response = $proxmoxVE->create($this->getPVE($nodeID).'lxc', $arr);
        return $response;
    }

    public function recreate($nodeID, $serverID, $ostemplate, $cores, $memory, $password, $disc, $swap, array $ip, $addr_count, $hostname, $storage, $network_rate = 60){

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $arr = [
            'ostemplate' => 'local:vztmpl/'.$ostemplate,
            'vmid' => $serverID,
            'cores' => $cores,
            'cpulimit' => $cores,
            'cpuunits' => 1024,
            'hostname' => $hostname,
            'memory' => $memory,
            'password' => $password,
            'rootfs' => $disc,
            'swap' => $swap,
            'storage' => $storage,
            'unprivileged' => true,
        ];
        $i = 0;
        foreach ($ip as $key => $address) {

            if($addr_count >= $i+1){
                $arr['net' . $i] = 'name=eth' . $i . ',ip=' . $address['ip'] . '/' . $address['cidr'] . ',gw=' . $address['gateway'] . ',bridge=vmbr0,rate='.$network_rate.',hwaddr=' . $address['mac_address'];

                //$update = self::db()->prepare("UPDATE `ip_addresses` SET `service_id`=:service_id,`service_type`=:service_type WHERE `id`=:id");
                //$update->execute(array(":service_id" => $serverID, ":service_type" => 'VPS', ":id" => $address['id']));

                $i++;
            }

        }


        $response = $proxmoxVE->create($this->getPVE($nodeID).'lxc', $arr);
        return $response;
    }

    public function correctNetwork($nodeID, $serverID)
    {

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $i = 0;
        $SQL = self::db()->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id");
        $SQL->execute(array(":service_id" => $serverID));
        while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {
            $ip_addrs[] = $row;
            $i++;
        }
        $addr_count = $i;

        $i = 0;
        $arr = [];
        foreach ($ip_addrs as $key => $address) {
            if($addr_count >= $i){
                $arr['net' . $i] = 'name=eth' . $i . ',ip=' . $address['ip'] . '/' . $address['cidr'] . ',gw=' . $address['gateway'] . ',bridge=vmbr0,rate=60,hwaddr=' . $address['mac_address'];
                $i++;
            }
        }

        $response = $proxmoxVE->set($this->getPVE($nodeID).'lxc/'.$serverID.'/config/', $arr);
        return $response;

    }

    public function correctCores($nodeID, $serverID, $cores)
    {

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $response = $proxmoxVE->set($this->getPVE($nodeID).'lxc/'.$serverID.'/config/', [
            'cpulimit' => $cores,
            'cores' => $cores,
        ]);
        return $response;

    }

    public function correctMemory($nodeID, $serverID, $memory)
    {

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $response = $proxmoxVE->set($this->getPVE($nodeID).'lxc/'.$serverID.'/config/', [
            'memory' => $memory,
        ]);
        return $response;

    }

    public function correctDisk($nodeID, $serverID, $disk)
    {

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $response = $proxmoxVE->set($this->getPVE($nodeID).'lxc/'.$serverID.'/resize/', [
            'size' => $disk.'G',
            'disk' => 'rootfs',
        ]);
        return $response;

    }

    public function deleteServer($nodeID, $serverID){

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $response = $proxmoxVE->delete($this->getPVE($nodeID).'lxc/'.$serverID);
        return $response;

    }


    public function getStatus($nodeID, $serverID){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->get($this->getPVE($nodeID).'lxc/'.$serverID.'/status/current');

        return $response;

    }

    public function getCPULoad($nodeID, $serverID){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $serverStatus = $proxmoxVE->get($this->getPVE($nodeID).'lxc/'.$serverID.'/status/current');
        $serverStatus = json_decode($serverStatus);

        $response = round($serverStatus->data->cpu * 100);
        $response = $response > 100 ? 100 : $response;

        return $response;

    }

    public function getMemoryLoad($nodeID, $serverID){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $serverStatus = $proxmoxVE->get($this->getPVE($nodeID).'lxc/'.$serverID.'/status/current');
        $serverStatus = json_decode($serverStatus);

        $response = round($serverStatus->data->mem / $serverStatus->data->maxmem * 100);
        $response = $response > 100 ? 100 : $response;

        return $response;

    }

    public function getDiscUsage($nodeID, $serverID, $discSize){

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $serverStatus = $proxmoxVE->get($this->getPVE($nodeID).'lxc/'.$serverID.'/status/current');
        $serverStatus = json_decode($serverStatus);

        $response = round($serverStatus->data->disk /1024/1024/1024 / $discSize * 100);
        $response = $response > 100 ? 100 : $response;

        return $response;

    }

    public function getOS($os_id)
    {

        $SQL = self::db()->prepare("SELECT * FROM `vm_server_os` WHERE `template` = :id");
        $SQL->execute(array(':id' => $os_id));
        $response = $SQL->fetch(PDO::FETCH_ASSOC);

        return $response['name'];
    }

    public function exec($node_id, $vm_id, $command)
    {
        $getServerCredentials = self::db()->prepare("SELECT * FROM `vm_host_nodes` WHERE `id` = :nodeID");
        $getServerCredentials->execute(array(":nodeID" => $node_id));
        $credentials = $getServerCredentials->fetch(PDO::FETCH_ASSOC);

        $ssh = new SSH2($credentials['hostname'],45);
        if (!$ssh->login('root', $credentials['root_password'])) {
            return ('Login Failed');
        }

//        return $ssh->exec('./lxc-exec '.$vm_id.' '.$command);
        return $ssh->exec('pct exec '.$vm_id.' /bin/sh -- -c "'.$command.'"');
    }

}