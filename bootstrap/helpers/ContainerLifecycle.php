<?php

function stopContainers(array $containerNames, $server, int $timeout = 300): void
{
    try {
        foreach ($containerNames as $containerName) {
            try {
                instant_remote_process([
                    "docker stop --time={$timeout} {$containerName}",
                ], $server, false);
            } catch (\Exception $e) {
                instant_remote_process([
                    "docker kill {$containerName}",
                ], $server, false);
            }
            removeContainer($containerName, $server);
        }
    } catch (\Exception $e) {
        throw new \RuntimeException('Failed to stop containers: '.$e->getMessage());
    }
}

function removeContainer(string $containerName, $server)
{
    instant_remote_process(["docker rm -f $containerName"], server: $server, throwError: false);
}

function deleteConnectedNetworks($uuid, $server) // delete_connected_networks
{
    instant_remote_process(["docker network disconnect {$uuid} coolify-proxy"], $server, false);
    instant_remote_process(["docker network rm {$uuid}"], $server, false);
}

function restartContainers(array $containerNames, $server, int $timeout = 60): void
{
    try {
        foreach ($containerNames as $containerName) {
            instant_remote_process([
                "docker restart --time={$timeout} {$containerName}",
            ], $server);
        }
    } catch (\Exception $e) {
        throw new \RuntimeException('Failed to restart containers: '.$e->getMessage());
    }
}
