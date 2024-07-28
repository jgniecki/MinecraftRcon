<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftRcon;

use DevLancer\MinecraftRcon\Exception\NotConnectedException;

class Rcon
{
    private string $host;
    private int $port;
    private string $password;
    private int $timeout;

    /**
     * @var resource|null
     */
    private $socket = null;
    private bool $authorized = false;
    private ?string $lastResponse = null;

    const PACKET_AUTHORIZE = 5;
    const PACKET_COMMAND = 6;
    const SERVERDATA_AUTH = 3;
    const SERVERDATA_AUTH_RESPONSE = 2;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;

    public function __construct(string $host, int $port, string $password, int $timeout = 3)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    /**
     * Get the latest response from the server.
     *
     * @return null|string
     */
    public function getResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * Connect to a server.
     *
     * @return boolean
     */
    public function connect(): bool
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, (float) $this->timeout);

        if (!$this->socket) {
            $this->lastResponse = $errstr;
            return false;
        }

        //set timeout
        stream_set_timeout($this->socket, 3, 0);
        return $this->authorize();
    }

    /**
     * Disconnect from server.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->authorized = false;

        if ($this->socket) {
            fclose($this->socket);
        }
    }

    /**
     * True if socket is connected and authorized.
     *
     * @return boolean
     */
    public function isConnected(): bool
    {
        return $this->authorized;
    }

    /**
     * Send a command to the connected server.z
     *
     * @param string $command
     * @return bool
     * @throws NotConnectedException
     */
    public function sendCommand(string $command): bool
    {
        if (!$this->isConnected()) {
            throw new NotConnectedException('The connection has not been established.');
        }

        // send command packet
        $this->writePacket(self::PACKET_COMMAND, self::SERVERDATA_EXECCOMMAND, $command);

        // get response
        $responsePacket = $this->readPacket();
        if (isset($responsePacket['id']) &&  $responsePacket['id'] == self::PACKET_COMMAND) {
            if (isset($responsePacket['type']) && $responsePacket['type'] == self::SERVERDATA_RESPONSE_VALUE) {
                if(!isset($responsePacket['body']) || !is_string($responsePacket['body'])) {
                    $this->lastResponse = null;
                } else {
                    $this->lastResponse = $responsePacket['body'];
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Log into the server with the given credentials.
     *
     * @return boolean
     */
    private function authorize(): bool
    {
        $this->writePacket(self::PACKET_AUTHORIZE, self::SERVERDATA_AUTH, $this->password);
        $responsePacket = $this->readPacket();

        if (isset($responsePacket['type']) && $responsePacket['type'] == self::SERVERDATA_AUTH_RESPONSE) {
            if (isset($responsePacket['id']) && $responsePacket['id'] == self::PACKET_AUTHORIZE) {
                $this->authorized = true;
                return true;
            }
        }

        $this->disconnect();
        return false;
    }

    /**
     * Writes a packet to the socket stream.
     *
     * @param $packetId
     * @param $packetType
     * @param string $packetBody
     *
     * @return void
     */
    private function writePacket($packetId, $packetType, string $packetBody): void
    {
        /*
		Size			32-bit little-endian Signed Integer	 	Varies, see below.
		ID				32-bit little-endian Signed Integer		Varies, see below.
		Type	        32-bit little-endian Signed Integer		Varies, see below.
		Body		    Null-terminated ASCII String			Varies, see below.
		Empty String    Null-terminated ASCII String			0x00
		*/

        //create packet
        $packet = pack('VV', $packetId, $packetType);
        $packet = $packet . $packetBody . "\x00";
        $packet = $packet . "\x00";

        // get packet size.
        $packetSize = strlen($packet);

        // attach size to packet.
        $packet = pack('V', $packetSize) . $packet;

        // write packet.
        fwrite($this->socket, $packet, strlen($packet));
    }

    /**
     * Read a packet from the socket stream.
     *
     * @return array
     */
    private function readPacket(): array
    {
        //get packet size.
        $size_data = fread($this->socket, 4);
        $size_pack = unpack('V1size', $size_data);
        $size = $size_pack['size'];

        // if size is > 4096, the response will be in multiple packets.
        // this needs to be address. get more info about multi-packet responses
        // from the RCON protocol specification at
        // https://developer.valvesoftware.com/wiki/Source_RCON_Protocol
        // currently, this script does not support multi-packet responses.

        $packet_data = fread($this->socket, $size);
        $packet_pack = unpack('V1id/V1type/a*body', $packet_data);
        if (!$packet_pack)
            return [];

        return $packet_pack;
    }
}
