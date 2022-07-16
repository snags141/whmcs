<?php

/**
MIT License

Copyright (c) 2018-2019 Stepan Fedotov <stepan@wisp.gg>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
**/

if(!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

function wisp_GetHostname(array $params) {
    $hostname = $params['serverhostname'];
    if ($hostname === '') throw new Exception('Could not find the panel\'s hostname - did you configure server group for the product?');

    // For whatever reason, WHMCS converts some characters of the hostname to their literal meanings (- => dash, etc) in some cases
    foreach([
        'DOT' => '.',
        'DASH' => '-',
    ] as $from => $to) {
        $hostname = str_replace($from, $to, $hostname);
    }

    if(ip2long($hostname) !== false) $hostname = 'http://' . $hostname;
    else $hostname = ($params['serversecure'] ? 'https://' : 'http://') . $hostname;

    return rtrim($hostname, '/');
}

function wisp_API(array $params, $endpoint, array $data = [], $method = "GET", $dontLog = false) {
    $url = wisp_GetHostname($params) . '/api/application/' . $endpoint;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($curl, CURLOPT_USERAGENT, "WISP-WHMCS");
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_POSTREDIR, CURL_REDIR_POST_301);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);

    $headers = [
        "Authorization: Bearer " . $params['serverpassword'],
        "Accept: Application/vnd.wisp.v1+json",
    ];

    if($method === 'POST' || $method === 'PATCH') {
        $jsonData = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        array_push($headers, "Content-Type: application/json");
        array_push($headers, "Content-Length: " . strlen($jsonData));
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $responseData = json_decode($response, true);
    $responseData['status_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if($responseData['status_code'] === 0 && !$dontLog) logModuleCall("WISP-WHMCS", "CURL ERROR", curl_error($curl), "");

    curl_close($curl);

    if(!$dontLog) logModuleCall("WISP-WHMCS", $method . " - " . $url,
        isset($data) ? json_encode($data) : "",
        print_r($responseData, true));

    return $responseData;
}

function wisp_Error($func, $params, Exception $err) {
    logModuleCall("WISP-WHMCS", $func, $params, $err->getMessage(), $err->getTraceAsString());
}

function wisp_MetaData() {
    return [
        "DisplayName" => "WISP",
        "APIVersion" => "1.1",
        "RequiresServer" => true,
    ];
}

function wisp_ConfigOptions() {
    return [
        "cpu" => [
            "FriendlyName" => "CPU Limit (%)",
            "Description" => "Amount of CPU to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "disk" => [
            "FriendlyName" => "Disk Space (MB)",
            "Description" => "Amount of Disk Space to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "memory" => [
            "FriendlyName" => "Memory (MB)",
            "Description" => "Amount of Memory to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "swap" => [
            "FriendlyName" => "Swap (MB)",
            "Description" => "Amount of Swap to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "location_id" => [
            "FriendlyName" => "Location ID",
            "Description" => "ID of the Location to automatically deploy to.",
            "Type" => "text",
            "Size" => 10,
        ],
        "dedicated_ip" => [
            "FriendlyName" => "Dedicated IP",
            "Description" => "Assign dedicated ip to the server (optional)",
            "Type" => "yesno",
        ],
        "nest_id" => [
            "FriendlyName" => "Nest ID",
            "Description" => "ID of the Nest for the server to use.",
            "Type" => "text",
            "Size" => 10,
        ],
        "egg_id" => [
            "FriendlyName" => "Egg ID",
            "Description" => "ID of the Egg for the server to use.",
            "Type" => "text",
            "Size" => 10,
        ],
        "io" => [
            "FriendlyName" => "Block IO Weight",
            "Description" => "Block IO Adjustment number (10-1000)",
            "Type" => "text",
            "Size" => 10,
            "Default" => "500",
        ],
        "pack_id" => [
            "FriendlyName" => "Pack ID",
            "Description" => "ID of the Pack to install the server with (optional)",
            "Type" => "text",
            "Size" => 10,
        ],
        "port_range" => [
            "FriendlyName" => "Port Range",
            "Description" => "Port ranges seperated by comma to assign to the server (Example: 25565-25570,25580-25590) (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "startup" => [
            "FriendlyName" => "Startup",
            "Description" => "Custom startup command to assign to the created server (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "image" => [
            "FriendlyName" => "Image",
            "Description" => "Custom Docker image to assign to the created server (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "databases" => [
            "FriendlyName" => "Databases",
            "Description" => "Client will be able to create this amount of databases for their server (optional)",
            "Type" => "text",
            "Size" => 10,
        ],
            "server_name" => [
            "FriendlyName" => "Server Name",
            "Description" => "The name of the server as shown on the panel (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "oom_disabled" => [
            "FriendlyName" => "Disable OOM Killer",
            "Description" => "Should the Out Of Memory Killer be disabled (optional)",
            "Type" => "yesno",
        ],
        "backup_megabytes_limit" => [
            "FriendlyName" => "Backup Size Limit",
            "Description" => "Amount in megabytes the server can use for backups (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "additional_ports" => [
            "FriendlyName" => "Additional Ports",
            "Description" => "Additional ports to assign to the server. See the module readme for instructions: <a href=\"https://github.com/wisp-gg/whmcs/\" target=\"_blank\">View Readme</a> (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "additional_port_fail_mode" => [
            "FriendlyName" => "Additional Port Failure Mode",
            "Type" => "dropdown",
            "Options" => [
                'continue' => 'Continue',
                'stop' => 'Stop',
            ],
            "Description" => "Determines whether server creation will continue if none of your nodes are able to satisfy the additional port allocation. See the module readme for more information: <a href=\"https://github.com/wisp-gg/whmcs/\" target=\"_blank\">View Readme</a>",
            "Default" => "continue",
        ],
    ];
}

function wisp_TestConnection(array $params) {
    $solutions = [
        0 => "Check module debug log for more detailed error.",
        401 => "Authorization header either missing or not provided.",
        403 => "Double check the password (which should be the Application Key).",
        404 => "Result not found.",
        422 => "Validation error.",
        500 => "Panel errored, check panel logs.",
    ];

    $err = "";
    try {
        $response = wisp_API($params, 'nodes');

        if($response['status_code'] !== 200) {
            $status_code = $response['status_code'];
            $err = "Invalid status_code received: " . $status_code . ". Possible solutions: "
                . (isset($solutions[$status_code]) ? $solutions[$status_code] : "None.");
        } else {
            if($response['meta']['pagination']['count'] === 0) {
                $err = "Authentication successful, but no nodes are available.";
            }
        }
    } catch(Exception $e) {
        wisp_Error(__FUNCTION__, $params, $e);
        $err = $e->getMessage();
    }

    return [
        "success" => empty($err),
        "error" => $err,
    ];
}

function wisp_GetOption(array $params, $id, $default = NULL) {
    $options = wisp_ConfigOptions();

    $friendlyName = $options[$id]['FriendlyName'];
    if(isset($params['configoptions'][$friendlyName]) && $params['configoptions'][$friendlyName] !== '') {
        return $params['configoptions'][$friendlyName];
    } else if(isset($params['configoptions'][$id]) && $params['configoptions'][$id] !== '') {
        return $params['configoptions'][$id];
    } else if(isset($params['customfields'][$friendlyName]) && $params['customfields'][$friendlyName] !== '') {
        return $params['customfields'][$friendlyName];
    } else if(isset($params['customfields'][$id]) && $params['customfields'][$id] !== '') {
        return $params['customfields'][$id];
    }

    $found = false;
    $i = 0;
    foreach(wisp_ConfigOptions() as $key => $value) {
        $i++;
        if($key === $id) {
            $found = true;
            break;
        }
    }

    if($found && isset($params['configoption' . $i]) && $params['configoption' . $i] !== '') {
        return $params['configoption' . $i];
    }

    return $default;
}

function wisp_CreateAccount(array $params) {
    try {
        // Checking if the server ID already exists
        $serverId = wisp_GetServerID($params);
        if(isset($serverId)) throw new Exception('Failed to create server because it is already created.');

        // Create or fetch the user account
        $userResult = wisp_API($params, 'users/external/' . $params['clientsdetails']['uuid']);
        if($userResult['status_code'] === 404) {
            $userResult = wisp_API($params, 'users?search=' . urlencode($params['clientsdetails']['email']));
            if($userResult['meta']['pagination']['total'] === 0) {
                $userResult = wisp_API($params, 'users', [
                    'email' => $params['clientsdetails']['email'],
                    'first_name' => empty($params['clientsdetails']['firstname']) ? 'Unknown' : $params['clientsdetails']['firstname'],
                    'last_name' => empty($params['clientsdetails']['lastname']) ? 'User' : $params['clientsdetails']['lastname'],
                    'external_id' => $params['clientsdetails']['uuid'],
                ], 'POST');
            } else {
                foreach($userResult['data'] as $key => $value) {
                    if($value['attributes']['email'] === $params['clientsdetails']['email']) {
                        $userResult = array_merge($userResult, $value);
                        break;
                    }
                }
                $userResult = array_merge($userResult, $userResult['data'][0]);
            }
        }

        if($userResult['status_code'] === 200 || $userResult['status_code'] === 201) {
            $userId = $userResult['attributes']['id'];
        } else {
            throw new Exception('Failed to create user, received error code: ' . $userResult['status_code'] . '. Enable module debug log for more info.');
        }

        // Get egg data
        $nestId = wisp_GetOption($params, 'nest_id');
        $eggId = wisp_GetOption($params, 'egg_id');

        $eggData = wisp_API($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $environment = [];
        foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $default = $attr['default_value'];
            $friendlyName = wisp_GetOption($params, $attr['name']);
            $envName = wisp_GetOption($params, $attr['env_variable']);

            if(isset($friendlyName)) $environment[$var] = $friendlyName;
            elseif(isset($envName)) $environment[$var] = $envName;
            else $environment[$var] = $default;
        }

        // Fetch given server parameters
        $name = wisp_GetOption($params, 'server_name', 'My Server');
        $memory = wisp_GetOption($params, 'memory');
        $swap = wisp_GetOption($params, 'swap');
        $io = wisp_GetOption($params, 'io');
        $cpu = wisp_GetOption($params, 'cpu');
        $disk = wisp_GetOption($params, 'disk');
        $pack_id = wisp_GetOption($params, 'pack_id');
        $location_id = wisp_GetOption($params, 'location_id');
        $dedicated_ip = wisp_GetOption($params, 'dedicated_ip') ? true : false;
        $port_range = wisp_GetOption($params, 'port_range');
        $additional_ports = wisp_GetOption($params, 'additional_ports');
        $additional_port_fail_mode = wisp_GetOption($params, 'additional_port_fail_mode');
        $port_range = isset($port_range) ? explode(',', $port_range) : [];
        $image = wisp_GetOption($params, 'image', $eggData['attributes']['docker_image']);
        $startup = wisp_GetOption($params, 'startup', $eggData['attributes']['startup']);
        $databases = wisp_GetOption($params, 'databases');
        $allocations = wisp_GetOption($params, 'allocations');
        $oom_disabled = wisp_GetOption($params, 'oom_disabled') ? true : false;
        $backup_megabytes_limit = wisp_GetOption($params, 'backup_megabytes_limit');
        $serverData = [
            'name' => $name,
            'user' => (int) $userId,
            'nest' => (int) $nestId,
            'egg' => (int) $eggId,
            'docker_image' => $image,
            'startup' => $startup,
            'oom_disabled' => $oom_disabled,
            'limits' => [
                'memory' => (int) $memory,
                'swap' => (int) $swap,
                'io' => (int) $io,
                'cpu' => (int) $cpu,
                'disk' => (int) $disk,
            ],
            'feature_limits' => [
                'databases' => $databases ? (int) $databases : null,
                'allocations' => (int) $allocations,
                'backup_megabytes_limit' => (int) $backup_megabytes_limit,
            ],
            'deploy' => [
                'locations' => [(int) $location_id],
                'dedicated_ip' => $dedicated_ip,
                'port_range' => $port_range,
            ],
            'environment' => $environment,
            'start_on_completion' => true,
            'external_id' => (string) $params['serviceid'],
        ];
        if(isset($pack_id)) $serverData['pack'] = (int) $pack_id;

        // Check if additional ports have been set
        if(isset($additional_ports) && $additional_ports != '') {
        
            // Query all nodes for the given location until we find an available set of ports
            // Get the list of additional ports to add
            //$additional_port_list = explode(",", $additional_ports);
            $additional_port_list = $additional_ports;
            // Get the server nodes for the specified location_id
            $nodes = getNodes($params, $location_id);
            
            // Get the port allocations for each node at this location and check if there's space for the additional ports
            if(isset($nodes)) {
                $alloc_success = false;
                foreach($nodes as $key => $node_id) {
                    logModuleCall("WISP-WHMCS", "Checking allocations for node: ".$node_id, "", "");
                    
                    // Get all the available allocations for this node
                    $available_allocations = getAllocations($params,$node_id);
                    
                    // Taking our additional allocation requirements and available node allocations, find a combination of available ports.
                    $final_allocations = findFreePorts($available_allocations, $additional_port_list);

                    if($final_allocations != false && $final_allocations['status'] == true) {
                        $alloc_success = true;
                        logModuleCall("WISP-WHMCS", "Successfully found an allocation. Setting primary allocation to ID " . $final_allocations['main_allocation_id'], "", "");
                        
                        $serverData['allocation']['default'] = intval($final_allocations['main_allocation_id']);
                        $serverData['allocation']['additional'] = $final_allocations['additional_allocation_ids'];
                        
                        // Update the environment parameters - additional allocations
                        foreach($final_allocations['additional_allocation_ports'] as $key => $port) {
                            // If the key given in the config had a value of NONE, don't worry about adding it to the environment parameters.
                            if(substr( $key, 0, 5 ) !== "NONE_") {
                                $serverData['environment'][$key] = $port;
                            }
                        }
                        // We successfully found and assigned an available allocation, break and check no more nodes.
                        break;
                    }
                    logModuleCall("WISP-WHMCS", "Failed to find an available allocation on node: ".$node_id, "", "");
                }
                if(!$alloc_success) {
                    // Failure handling logic
                    if($additional_port_fail_mode == "stop") {
                        throw new Exception('Couldn\'t find any nodes to satisfy the requested allocations.');
                    } else {
                        // Continue with normal deployment
                        $serverData['deploy']['port_range'] = $port_range;
                    }
                }
            } else {
                logModuleCall("WISP-WHMCS", "Unable to find any nodes at location ID: ".$loc_id, "", "");
                throw new Exception('Couldn\'t find any nodes satisfying the request at location: ' . $loc_id);
            }
        } else {
            // Continue with normal deployment
            $serverData['deploy']['port_range'] = $port_range;
        }

        // Create the game server
        $server = wisp_API($params, 'servers', $serverData, 'POST');

        // Catch API errors
        if($server['status_code'] === 400) throw new Exception('Couldn\'t find any nodes satisfying the request.');
        if($server['status_code'] !== 201 && $server['status_code'] !== 200) throw new Exception('Failed to create the server, received the error code: ' . $server['status_code'] . '. Enable module debug log for more info.');

        unset($params['password']);
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

// Function to allow backwards compatibility with death-droid's module
function wisp_GetServerID(array $params, $raw = false) {
    $serverResult = wisp_API($params, 'servers/external/' . $params['serviceid'], [], 'GET', true);
    if($serverResult['status_code'] === 200) {
        if($raw) return $serverResult;
        else return $serverResult['attributes']['id'];
    } else if($serverResult['status_code'] === 500) {
        throw new Exception('Failed to get server, panel errored. Check panel logs for more info.');
    }

    if(Capsule::schema()->hasTable('tbl_pterodactylproduct')) {
        $oldData = Capsule::table('tbl_pterodactylproduct')
            ->select('user_id', 'server_id')
            ->where('service_id', '=', $params['serviceid'])
            ->first();

        if(isset($oldData) && isset($oldData->server_id)) {
            if($raw) {
                $serverResult = wisp_API($params, 'servers/' . $oldData->server_id);
                if($serverResult['status_code'] === 200) return $serverResult;
                else throw new Exception('Failed to get server, received the error code: ' . $serverResult['status_code'] . '. Enable module debug log for more info.');
            } else {
                return $oldData->server_id;
            }
        }
    }
}

function wisp_SuspendAccount(array $params) {
    try {
        $serverId = wisp_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to suspend server because it doesn\'t exist.');

        $suspendResult = wisp_API($params, 'servers/' . $serverId . '/suspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Failed to suspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function wisp_UnsuspendAccount(array $params) {
    try {
        $serverId = wisp_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to unsuspend server because it doesn\'t exist.');

        $suspendResult = wisp_API($params, 'servers/' . $serverId . '/unsuspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Failed to unsuspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function wisp_TerminateAccount(array $params) {
    try {
        $serverId = wisp_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to terminate server because it doesn\'t exist.');

        $deleteResult = wisp_API($params, 'servers/' . $serverId, [], 'DELETE');
        if($deleteResult['status_code'] !== 204) throw new Exception('Failed to terminate the server, received error code: ' . $deleteResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function wisp_ChangePassword(array $params) {
    try {
        if($params['password'] === '') throw new Exception('The password cannot be empty.');

        $serverData = wisp_GetServerID($params, true);
        if(!isset($serverData)) throw new Exception('Failed to change password because linked server doesn\'t exist.');

        $userId = $serverData['attributes']['user'];
        $userResult = wisp_API($params, 'users/' . $userId);
        if($userResult['status_code'] !== 200) throw new Exception('Failed to retrieve user, received error code: ' . $userResult['status_code'] . '.');

        $updateResult = wisp_API($params, 'users/' . $serverData['attributes']['user'], [
            'email' => $userResult['attributes']['email'],
            'first_name' => $userResult['attributes']['first_name'],
            'last_name' => $userResult['attributes']['last_name'],

            'password' => $params['password'],
        ], 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to change password, received error code: ' . $updateResult['status_code'] . '.');

        unset($params['password']);
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function wisp_ChangePackage(array $params) {
    try {
        $serverData = wisp_GetServerID($params, true);
        if($serverData['status_code'] === 404 || !isset($serverData['attributes']['id'])) throw new Exception('Failed to change package of server because it doesn\'t exist.');
        $serverId = $serverData['attributes']['id'];

        $memory = wisp_GetOption($params, 'memory');
        $swap = wisp_GetOption($params, 'swap');
        $io = wisp_GetOption($params, 'io');
        $cpu = wisp_GetOption($params, 'cpu');
        $disk = wisp_GetOption($params, 'disk');
        $databases = wisp_GetOption($params, 'databases');
        $allocations = wisp_GetOption($params, 'allocations');
        $oom_disabled = wisp_GetOption($params, 'oom_disabled') ? true : false;
        $backup_megabytes_limit = wisp_GetOption($params, 'backup_megabytes_limit');
        $updateData = [
            'allocation' => $serverData['attributes']['allocation'],
            'memory' => (int) $memory,
            'swap' => (int) $swap,
            'io' => (int) $io,
            'cpu' => (int) $cpu,
            'disk' => (int) $disk,
            'oom_disabled' => $oom_disabled,
            'feature_limits' => [
                'databases' => (int) $databases,
                'allocations' => (int) $allocations,
                'backup_megabytes_limit' => (int) $backup_megabytes_limit,
            ],
        ];

        $updateResult = wisp_API($params, 'servers/' . $serverId . '/build', $updateData, 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to update build of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');

        $nestId = wisp_GetOption($params, 'nest_id');
        $eggId = wisp_GetOption($params, 'egg_id');
        $pack_id = wisp_GetOption($params, 'pack_id');
        $eggData = wisp_API($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $environment = [];
        foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $friendlyName = wisp_GetOption($params, $attr['name']);
            $envName = wisp_GetOption($params, $attr['env_variable']);

            if(isset($friendlyName)) $environment[$var] = $friendlyName;
            elseif(isset($envName)) $environment[$var] = $envName;
            elseif(isset($serverData['attributes']['container']['environment'][$var])) $environment[$var] = $serverData['attributes']['container']['environment'][$var];
            elseif(isset($attr['default_value'])) $environment[$var] = $attr['default_value'];
        }

        $image = wisp_GetOption($params, 'image', $serverData['attributes']['container']['image']);
        $startup = wisp_GetOption($params, 'startup', $serverData['attributes']['container']['startup_command']);
        $updateData = [
            'environment' => $environment,
            'startup' => $startup,
            'egg' => (int) $eggId,
            'pack' => (int) $pack_id,
            'image' => $image,
            'skip_scripts' => false,
        ];

        $updateResult = wisp_API($params, 'servers/' . $serverId . '/startup', $updateData, 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to update startup of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function wisp_LoginLink(array $params) {
    if($params['moduletype'] !== 'wisp') return;

    try {
        $serverId = wisp_GetServerID($params);
        if(!isset($serverId)) return;

        $hostname = wisp_GetHostname($params);
        echo '[<a href="'.$hostname.'/admin/servers/view/' . $serverId . '" target="_blank">Go to Service</a>]';
        echo '<p style="float: right">[<a href="https://github.com/wisp-gg/whmcs/issues" target="_blank">Report A Bug</a>]</p>';
    } catch(Exception $err) {
        // Ignore
    }
}

function wisp_ClientArea(array $params) {
    if($params['moduletype'] !== 'wisp') return;

    try {
        $serverData = wisp_GetServerID($params, true);
        if($serverData['status_code'] === 404 || !isset($serverData['attributes']['id'])) return;

        $hostname = wisp_GetHostname($params);

        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceurl' => $hostname . '/server/' . $serverData['attributes']['identifier'],
            ],
        ];
    } catch (Exception $err) {
        // Ignore
    }
}

/* Utility Functions */
function getNodes(array $params, int $loc_id) {
    /*
        Fetches and returns all nodes with a specific location_id
    */
    $filteredNodes = Array();
    $nodes = wisp_API($params, 'nodes');
    foreach($nodes['data'] as $key => $value) {
        $node_id = $value['attributes']['id'];
        if($value['attributes']['location_id'] == $loc_id) {
            array_push($filteredNodes, $node_id);
        }
    }
    return $filteredNodes;
}

function getAllocations(array $params, int $node_id){
    /*
        Gets the available allocations for a specific node_id
        and returns them in a format that can be more easily parsed.

        Output:
        Returns the available allocations in
        a more usable format for filtering
        Format:
        [
            [<ip_address>] => {
                [<port number>] => {
                    ['id'] = 1234;
                }
            },
            ['192.168.1.123'] => {
                ['1234'] => {
                    ['id'] = 1234;
                }z
            },
        ]
    */
    $available_allocations = array();
    $allocations = getPaginatedData($params,'nodes/'.$node_id.'/allocations');
    foreach($allocations as $key => $allocation) {
        $ip = $allocation['attributes']['ip'];
        $port = $allocation['attributes']['port'];
        if ($allocation['attributes']['assigned']!=true) {
            $available_allocations[$ip][$port]['id'] = $allocation['attributes']['id'];
        }
    }
    return $available_allocations;
}

function getPaginatedData($params,$url) {
    /*
        Makes a paginated API request and returns the response.
    */

    $results = array();
    // Fetch and parse first page of data
    $response = wisp_API($params, $url);
    foreach($response['data'] as $key => $value) {
        array_push($results, $value);
    }
    $current_page = $response['meta']['pagination']['current_page'];
    $total_pages = $response['meta']['pagination']['total_pages'];
    // Fetch and parse any remaining pages
    while ($total_pages > $current_page) {
        $next_page = intval($current_page)+1;
        $response = wisp_API($params, $url.'?page='.$next_page);
        foreach($response['data'] as $key => $value) {
            array_push($results, $value);
        }
        $current_page = $response['meta']['pagination']['current_page'];
        $total_pages = $response['meta']['pagination']['total_pages'];
    }
    return $results;
}

function findFreePorts(array $available_allocations, string $port_offsets) {
    /*
        This is the main logic that takes a list of available allocations
        and the required offsets and then finds the first available set.
        e.g. if port offsets +1 +2 and +4 are requested (format: 1,2,4)
        we take each port one by one and check if <port number> + 1,
        <port number> + 2 and <port number> + 4 are available.
        If all requested port allocations are available, they are returned.

        Inputs:
        $available_allocations      This is the first port in the range to test.
                                    All other ports will be checked based on the
                                    required offset from the first.

        $port_offsets               The json string of offsets from the first port that
                                    are required for the server.

        Outputs:
        $ports_found                The array of ports that were found available, based on the offsets
                                    the additional ports required.
    */
    
    $port_offsets_array = json_decode($port_offsets, true);

    // Iterate over available IP's
    foreach($available_allocations as $ip_addr => $ports) {
        $result = Array();
        $result['status'] = false;
        $main_allocation_id = "";
        $main_allocation_port = "";
        $additional_allocation_ids = Array();
        $additional_allocation_ports = Array();

        // Iterate over Ports
        logModuleCall("WISP-WHMCS", "Checking IP: ".$ip_addr, "", "");
        foreach($ports as $port => $portDetails) {
            $main_allocation_id = $portDetails['id'];
            $main_allocation_port = $port;
            $found_all = true;
            foreach($port_offsets_array as $port_offset => $environment) {
                $next_port = intval($port) + intval($port_offset);
                if(!isset($ports[$next_port])) {
                    // Port is not available
                    $found_all = false;
                } else {
                    // Port is available, add it to the array
                    array_push($additional_allocation_ids, strval($ports[$next_port]['id']));
                    //array_push($additional_allocation_ports, strval($next_port));

                    $additional_allocation_ports[$environment] = $next_port;
                }
            }
            if($found_all == true) {
                logModuleCall("WISP-WHMCS", "Found a game port allocation ID: ".$main_allocation_id, "", "");
                logModuleCall("WISP-WHMCS", "Found additional allocation ID's: ".print_r($additional_allocation_ids, true), "", "");
                logModuleCall("WISP-WHMCS", "Found additional allocation Ports: ".print_r($additional_allocation_ports, true), "", "");
                $result['main_allocation_id'] = $main_allocation_id;
                $result['main_allocation_port'] = $main_allocation_port;
                $result['additional_allocation_ids'] = $additional_allocation_ids;
                $result['additional_allocation_ports'] = $additional_allocation_ports;
                $result['status'] = true;
                return $result;
            } else {
                // Reset values in array for next run
                $additional_allocation_ids = Array();
                $additional_allocation_ports = Array();
            }
        }

        // Failed to find available set of ports based on requirements
        logModuleCall("WISP-WHMCS", "Failed to find available ports!", "", "");
        return false;
    }
}

