## WHMCS
WHMCS Module for the [WISP Panel](https://wisp.gg/).

## Configuration support
Please use the [WISP Discord](https://wisp.gg/discord) for configuration related support instead of GitHub issues.

## Installation
[Video Tutorial](https://www.youtube.com/watch?v=wURpRD9vfj4)  

1. Download/Git clone this repository.  
2. Move the ``wisp/`` folder into ``<path to whmcs>/modules/servers/``.
3. Create API Credentials with these permissions: ![Image](https://i.imgur.com/nzo0u8C.png)
4. In WHMCS navigate to Setup > Products/Services > Servers
5. Create new server, fill the name with anything you want, hostname as the url to the panel. For example: ``my-panel.panel.gg``
6. Change Server Type to WISP, leave username empty, fill the password field with your generated API Key.
7. Tick the "Secure" option if your panel is using SSL.
8. Confirm that everything works by clicking the Test Connection button -> Save Changes.
9. Go back to the Servers screen and press Create New Group, name it anything you want and choose the created server and press the Add button, Save Changes.
10. Navigate to Setup > Products/Services > Products/Services
11. Create your desired product (and product group if you haven't already) with the type of Other and product name of anything -> Continue.
12. Click the Module Settings tab, choose for Module Name WISP and for the Server Group the group you created in step 8.
13. Fill all non-optional fields, and you are good to go!

## Credits
[Dane](https://github.com/DaneEveritt) and [everyone else](https://github.com/Pterodactyl/Panel/graphs/contributors) involved in development of the Pterodactyl Panel.  
[death-droid](https://github.com/death-droid) for the original WHMCS module.  
[snags141](https://github.com/snags141/) for additional allocation support.

# FAQ

## Migrating from pterodactyl's module
This module is backwards compatible and requires no changing other than switching to this module.

## Overwriting values through configurable options
Overwriting values can be done through either Configurable Options or Custom Fields.  

Their name should be exactly what you want to overwrite.  
dedicated_ip => Will overwrite dedicated_ip if its ticked or not.  
Valid options: ``server_name, memory, swap, io, cpu, disk, nest_id, egg_id, pack_id, location_id, dedicated_ip, port_range, image, startup, databases, allocations, oom_disabled, username, backup_megabytes_limit``

This also works for any name of environment variable:  
Player Slots => Will overwrite the environment variable named "Player Slots" to its value.  

Useful trick: You can use the | separator to change the display name of the variable like this:  
dedicated_ip|Dedicated IP => Will be displayed as "Dedicated IP" but will work correctly.  

[Sample configuration for configurable memory](https://owo.whats-th.is/85JwhVX.png)

## Couldn't find any nodes satisfying the request.
This can be caused from any of the following: Wrong location, not enough disk space/CPU/RAM, or no allocations matching the provided criteria.

## The username/password field is empty, how does the user get credentials?
The customer gets an email from the panel to setup their account (incl. password) if they didn't have an account before. Otherwise they should be able to use their existing credentials.

## My game requires multiple ports allocated.
Configure the "**Additional Ports**" option in the module settings.
It expects a valid JSON string consisting of the parameter name or NONE, and a number representing a port as a numeric offset from the first available allocation.
E.g: If you enter `{"1":"NONE", "2":"NONE", "4":"NONE"}` and the first available port happens to be 25565, you'll get as additional allocations: 
* 25566 (First Port + 1)
* 25567 (First Port +2)
* 25569 (First Port +4)

(If they're available)

Note: I this option is set, it will override anything specified under "port_range" - Use one or the other, not both.

You'll also want to configure "**Additional Port Failure Mode**".
This determines what the module should do if there are no allocations available on any of the defined nodes.
* "Continue" - Continues creating the server but only with one allocation, whatever is available at the time. You'll need to manually go in after the server gets created to assign additional ports as required.
* "Stop" - Stops the server creation and raises an error.

## How to assign additional allocations to server parameters like RCON_PORT
See the table below for "Additional Ports" example values.
*These examples assume your WISP node has allocations available from 1000-2000.*

| Game | Required Ports  |Additional Ports Example  | Ports Assigned  |
| ------------ | ------------ | ------------ | ------------ |
| Rust | Game port and RCON port | `{"1":"RCON_PORT"}`  | Game Port: 1000, RCON_PORT: 1001|
| Arma 3 | Game port, Game port +1 for Steam Query, Game port + 2 for Steam Port, and Game port +4 for BattleEye |  `{"1":"NONE", "2":"NONE", "4":"NONE"}` | Game Port: 1000, Additional Ports: 1001, 1002, 1004 |
| Unturned | Game port, Game port +1 and Game port +2 | `{"1":"NONE", "2":"NONE"}` | Game Port: 1000, Additional Ports: 1001, 1002 |
| Project Zomboid | Game Port, Steam port and an additional port for every player. Let's say we want 10 additional ports for 10 players. | `{"1":"STEAM_PORT", "2":"NONE", "3":"NONE", "4":"NONE", "5":"NONE", "6":"NONE", "7":"NONE", "8":"NONE", "9":"NONE", "10":"NONE", "11":"NONE"}` | Game Port: 1000, Steam Port: 1001, Additional Ports: 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010, 1011|

**What does "NONE" mean?**
"NONE" means you want to assign the additional port to the server, but it doesn't need to be assigned to a server parameter. If instead you want to add a +1 port and assign it to the parameter "RCON_PORT" then you'd use `{"1":"RCON_PORT"}` for example.

## How to enable module debug log
1. In WHMCS 7 or below navigate to Utilities > Logs > Module Log. For WHMCS 8.x navigate to System Logs > Module Log in the left sidebar.
2. Click the Enable Debug Logging button.
3. Do the action that failed again and you will have required logs to debug the issue. All 404 errors can be ignored.
4. Remember to Disable Debug Logging if you are using this in production, as it's not recommended to have it enabled.