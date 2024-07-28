Minecraft Rcon
==================
![](https://img.shields.io/packagist/l/jgniecki/minecraft-rcon?style=for-the-badge)
![](https://img.shields.io/packagist/dt/jgniecki/minecraft-rcon?style=for-the-badge)
![](https://img.shields.io/github/v/release/jgniecki/MinecraftRcon?style=for-the-badge)
![](https://img.shields.io/packagist/php-v/jgniecki/minecraft-rcon?style=for-the-badge)

PHP library to request RCON for Minecraft servers
## Installation
### Using Composer
This Rcon library may be installed by issuing the following command:
```bash
$ composer require dev-lancer/minecraft-rcon
```

## Example
For this script to work, rcon must be enabled on the server, by setting `enable-rcon=true` in the server's `server.properties` file. A password must also be set, and provided in the script.

```php
$host = 'some.minecraftserver.com'; // Server host name or IP
$port = 25575;                      // Port rcon is listening on
$password = 'server-rcon-password'; // rcon.password setting set in server.properties
$timeout = 3;                       // How long to timeout.

use DevLancer\MinecraftRcon;

$rcon = new Rcon($host, $port, $password, $timeout);

if ($rcon->connect())
{
    if ($rcon->sendCommand("say Hello World!") === false) {
        //bad request
    } else {
        echo $rcon->getResponse(); //success
    }
} else {
    echo $rcon->getResponse(); //error
}
```
