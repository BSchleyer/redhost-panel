<?php

$pterodactyl = new Pterodactyl();
class Pterodactyl extends Controller
{

    public function createUser($external_id, $username, $email, $first_name, $last_name, $password, $language = 'de')
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        try {
            $response = $pterodactyl->createUser([
                'external_id' => $external_id,
                'username' => $username,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'password' => $password,
                'language' => $language
            ]);
            return $response;
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function create($name, $user, $egg_id, array $limits, array $feature_limits)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        $egg = $pterodactyl->egg(1, $egg_id);

        try {
            $response = $pterodactyl->createServer([
                'name' => $name,
                'user' => $user,
                'egg' => $egg_id,
                'limits' => $limits,
                'feature_limits' => $feature_limits,
                'docker_image' => $egg->dockerImage,
                'startup' => $egg->startup,
                'environment' => [
                    "SERVER_AUTOUPDATE" => '1',
                    "SERVER_JARFILE" => 'server.jar',
                    "VANILLA_VERSION" => '1.15.2',
                    "BUILD_NUMBER" => '1.15.2'
                ],
                'deploy' => [
                    'locations' => [1],
                    'dedicated_ip' => false,
                    'port_range' => []
                ],
                'start_on_completion' => true,
            ]);

            return $response;
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function delete($serverId)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        try {
            $pterodactyl->deleteServer($serverId);
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function updateServerBuild($serverId, array $limits, array $feature_limits, $allocation_id)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

//        $pterodactyl->updateServerDetails()

        try {
            $response = $pterodactyl->updateServerBuild($serverId, [
                'oom_disabled' => false,
                'allocation' => $allocation_id,
                'limits' => $limits,
                'feature_limits' => $feature_limits
            ]);

            return $response;
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function suspend($serverId)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        try {
            $pterodactyl->suspendServer($serverId);
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function unsuspend($serverId)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        try {
            $pterodactyl->unsuspendServer($serverId);
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

    public function reinstall($serverId)
    {
        $pterodactyl = new \HCGCloud\Pterodactyl\Pterodactyl(env('PTERODACTYL_API_KEY'), env('PTERODACTYL_BASE_URL'));

        try {
            $pterodactyl->reinstallServer($serverId);
        } catch(\HCGCloud\Pterodactyl\Exceptions\ValidationException $e){
            return $e->errors();
        }
    }

}