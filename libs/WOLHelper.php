<?php

declare(strict_types=1);

trait WOLHelper
{
    //Weckt das einzelne Gerät mit Broadcast- und MAC-Adresse aus der Konfiguration
    public function WakeOnLan(): void
    {
        $this->SendMagicPacket($this->ReadPropertyString('BroadcastAddress'), $this->ReadPropertyString('MACAddress'));
    }

    protected function IsValidMACAddress(string $mac): bool
    {
        return preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac) === 1;
    }

    protected function SendMagicPacket(string $addr, string $mac): void
    {
        if ($addr == '' || $mac == '') {
            $this->SendDebug(__FUNCTION__, 'Broadcast or Mac Address is missing', 0);
            return;
        }
        if (!$this->IsValidMACAddress($mac)) {
            $this->SendDebug(__FUNCTION__, 'MAC Address is invalid', 0);
            return;
        }
        if (!function_exists('socket_create')) {
            $this->SendDebug(__FUNCTION__, 'PHP extension sockets is not available', 0);
            return;
        }

        $addr_byte = explode(':', $mac);
        $hw_addr = '';
        for ($a = 0; $a < 6; $a++) {
            $hw_addr .= chr(hexdec($addr_byte[$a]));
        }
        $msg = chr(255) . chr(255) . chr(255) . chr(255) . chr(255) . chr(255);
        for ($a = 1; $a <= 16; $a++) {
            $msg .= $hw_addr;
        }

        // send it to the broadcast address using UDP
        $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($s === false) {
            $this->SendDebug('Error creating socket!', 'Error code is ' . socket_last_error() . ' - ' . socket_strerror(socket_last_error()), 0);
            return;
        }

        // setting a broadcast option to socket:
        if (!@socket_set_option($s, SOL_SOCKET, SO_BROADCAST, 1)) {
            $this->SendDebug('socket_set_option(SO_BROADCAST) failed', socket_strerror(socket_last_error($s)), 0);
        }
        if (!@socket_set_option($s, SOL_SOCKET, SO_REUSEADDR, 1)) {
            $this->SendDebug('socket_set_option(SO_REUSEADDR) failed', socket_strerror(socket_last_error($s)), 0);
        }
        $result = @socket_sendto($s, $msg, strlen($msg), 0, $addr, 2050);
        if ($result === false) {
            $this->SendDebug('Error sending Magic Packet', socket_strerror(socket_last_error($s)), 0);
        } else {
            $this->SendDebug('Result', 'Magic Packet sent (' . $result . ') to ' . $addr . ', MAC=' . $mac, 0);
        }
        socket_close($s);
    }
}
