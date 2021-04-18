<?php
use ProxmoxVE\Proxmox;

$kvm = new KVM();
class KVM extends Controller
{

    public function getServerCredentials($nodeID)
    {

        $getServerCredentials = self::db()->prepare("SELECT * FROM `vm_host_nodes` WHERE `id` = :nodeID");
        $getServerCredentials->execute(array(":nodeID" => $nodeID));
        $credentials = $getServerCredentials->fetch(PDO::FETCH_ASSOC);

        return $serverCredentials = [ 'hostname' => $credentials['hostname'], 'username' => $credentials['username'], 'password' => $credentials['password'], 'realm' => $credentials['realm'] ];
    }

    public function getNodeStats($nodeID)
    {

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->get('/nodes');

        return $response;

    }

    public function getPVE($nodeID)
    {

        $getServerCredentials = self::db()->prepare("SELECT * FROM `vm_host_nodes` WHERE `id` = :nodeID");
        $getServerCredentials->execute(array(":nodeID" => $nodeID));
        $credentials = $getServerCredentials->fetch(PDO::FETCH_ASSOC);

        $nodeName = $credentials['name'];

        return '/nodes/'.$nodeName.'/';
    }

    public function startServer($nodeID, $serverID)
    {

        $status = $this->getStatus($nodeID, $serverID);
        $status = json_decode($status);


        if($status->data->status == 'stopped'){
            $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
            $proxmoxVE->setResponseType('json');
            $response = $proxmoxVE->create($this->getPVE($nodeID).'qemu/'.$serverID.'/status/start');

            return $response;
        }

        return FALSE;

    }

    public function stopServer($nodeID, $serverID)
    {

        $status = $this->getStatus($nodeID, $serverID);
        $status = json_decode($status);


        if($status->data->status == 'running'){
            $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
            $proxmoxVE->setResponseType('json');
            $response = $proxmoxVE->create($this->getPVE($nodeID).'qemu/'.$serverID.'/status/stop');

            return $response;
        }

        return FALSE;

    }

    public function shutdown($nodeID, $serverID)
    {

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->create($this->getPVE($nodeID).'qemu/'.$serverID.'/status/shutdown');

        return $response;

    }

    public function deleteServer($nodeID, $serverID){

        $proxmoxVE = new Proxmox($this->getServerCredentials($nodeID));
        $proxmoxVE->setResponseType('json');

        $response = $proxmoxVE->delete($this->getPVE($nodeID).'qemu/'.$serverID);
        return $response;

    }

    public function getStatus($nodeID, $serverID)
    {

        $proxmoxVE = new Proxmox(($this->getServerCredentials($nodeID)));
        $proxmoxVE->setResponseType('json');
        $response = $proxmoxVE->get($this->getPVE($nodeID) . 'qemu/' . $serverID . '/status/current');

        return $response;

    }

}