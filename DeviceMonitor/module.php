<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/WOLHelper.php';
require_once __DIR__ . '/../libs/vendor/SymconModulHelper/VariableProfileHelper.php';

class DeviceMonitor extends IPSModuleStrict
{
    use WOLHelper;
    use VariableProfileHelper;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('ListOfHosts', false);
        $this->RegisterPropertyString('IPAddress', '');
        $this->RegisterPropertyString('HostsList', '[]');
        $this->RegisterPropertyString('BroadcastAddress', '');
        $this->RegisterPropertyString('MACAddress', '');
        $this->RegisterPropertyBoolean('ActiveTries', false);
        $this->RegisterPropertyInteger('Tries', 20);
        $this->RegisterPropertyInteger('PingTimeout', 1000);
        $this->RegisterPropertyInteger('Interval', 20);
        $this->RegisterPropertyBoolean('WakeOnLan', false);

        $this->RegisterTimer('DM_UpdateTimer', 0, 'DM_UpdateStatus($_IPS[\'TARGET\']);');

        $this->RegisterVariablenProfiles();
        $this->RegisterVariableBoolean('DeviceStatus', $this->Translate('State'), 'DM.Status', 0);
        $this->RegisterVariableInteger('LastSeen', $this->Translate('Last seen'), '~UnixTimestamp', 1);
        $this->RegisterVariableInteger('LastOffline', $this->Translate('Last offline'), '~UnixTimestamp', 1);

    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetValue('DeviceStatus', false);
        //Buffer resetten, damit die Zählung neu beginnen kann.
        $this->SetBuffer('DeviceStatus', 'false');
        $this->SetBuffer('TriesDeviceStatus', '0');

        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);

        $hostsList = json_decode($this->ReadPropertyString('HostsList'), true);
        $hostsListValid = is_array($hostsList);
        if (!$hostsListValid) {
            $hostsList = [];
        }
        $ListOfHosts = $this->ReadPropertyBoolean('ListOfHosts');

        //Idents aller Variablen, die zur aktuellen Liste gehören
        $validIdents = [];
        foreach ($hostsList as $host) {
            $identBase = 'lst_' . str_replace('.', '_', $host['IPAddress'] ?? '');
            $validIdents[] = $identBase;
            $validIdents[] = $identBase . '_LastSeen';
            $validIdents[] = $identBase . '_LastOffline';
        }
        //Variablen von Geräten entfernen, die nicht mehr in der Liste stehen
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $childObject = IPS_GetObject($childID);
            if ($childObject['ObjectType'] == OBJECTTYPE_VARIABLE
                && strpos($childObject['ObjectIdent'], 'lst_') === 0
                && !in_array($childObject['ObjectIdent'], $validIdents, true)) {
                $this->UnregisterVariable($childObject['ObjectIdent']);
            }
        }

        $variablePosition = 2;
        foreach ($hostsList as $key => $host) {
            $IdentState = 'lst_' . str_replace('.', '_', $host['IPAddress']);
            $IdentLastSeen = 'lst_' . str_replace('.', '_', $host['IPAddress']) . '_LastSeen';
            $IdentLastOffline = 'lst_' . str_replace('.', '_', $host['IPAddress']) . '_LastOffline';

            $variablePosition++;
            $this->MaintainVariable($IdentState, $this->Translate('State') . ' ' . $host['name'], 0, 'DM.Status', $variablePosition, $ListOfHosts);
            if ($ListOfHosts) {
                //Buffer resetten, damit die Zählung neu beginnen kann.
                $this->SetBuffer($IdentState, '');
                $this->SetBuffer('Tries' . $IdentState, '0');
            }
            $variablePosition++;
            $this->MaintainVariable($IdentLastSeen, $this->Translate('Last seen') . ' ' . $host['name'], 1, '~UnixTimestamp', $variablePosition, $ListOfHosts);
            $variablePosition++;
            $this->MaintainVariable($IdentLastOffline, $this->Translate('Last offline') . ' ' . $host['name'], 1, '~UnixTimestamp', $variablePosition, $ListOfHosts);
        }

        $WOL = $this->ReadPropertyBoolean('WakeOnLan');
        $this->MaintainVariable('DeviceWOL', $this->Translate('Wake On Lan'), 1, 'DM.WOL', 0, $this->ReadPropertyBoolean('WakeOnLan') == true && $this->ReadPropertyBoolean('ListOfHosts') == false);
        if ($this->ReadPropertyBoolean('WakeOnLan') && $this->ReadPropertyBoolean('ListOfHosts') == false) {
            $this->SetValue('DeviceWOL', 1);
            $this->EnableAction('DeviceWOL');
        }

        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetTimerInterval('DM_UpdateTimer', 0);
            $this->SetStatus(104);
            return;
        }

        //Konfiguration prüfen, bevor gepingt wird
        $configValid = $this->ReadPropertyInteger('Interval') > 0 && $this->ReadPropertyInteger('PingTimeout') > 0;
        if ($ListOfHosts) {
            $configValid = $configValid && $hostsListValid && count($hostsList) > 0;
            foreach ($hostsList as $host) {
                if (trim($host['IPAddress'] ?? '') === '') {
                    $configValid = false;
                }
            }
        } else {
            $configValid = $configValid && trim($this->ReadPropertyString('IPAddress')) !== '';
        }
        if (!$configValid) {
            $this->SetTimerInterval('DM_UpdateTimer', 0);
            $this->SetStatus(201);
            return;
        }

        $this->SetTimerInterval('DM_UpdateTimer', $this->ReadPropertyInteger('Interval') * 1000);
        $this->UpdateStatus();
        $this->SetStatus(102);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->ReadPropertyBoolean('ListOfHosts')) {
            //Ein Host unsichtbar setzen, wenn die Liste aktiv ist
            $form['elements'][2]['visible'] = false;
            //Liste von Hosts
            $form['elements'][3]['visible'] = true;
            //WOL unsichtbar setzen, wenn die Liste aktiv ist
            $form['elements'][10]['visible'] = false;
        }
        return json_encode($form);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {

        //Wenn der Status sich der Instanz ändert
        if ($Message == IM_CHANGESTATUS) {
            switch ($Data[0]) {
                case 104: //Inaktiv Variable auf false setzen
                    $this->SetValue('DeviceStatus', false);
                    break;
                default:
                    # code...
                    break;
            }
        }
    }

    public function UpdateStatus(): void
    {
        $ListOfHosts = $this->ReadPropertyBoolean('ListOfHosts');
        $deviceState = false;

        //Liste der Hosts durch gehen und pingen
        if ($ListOfHosts) {
            $hostsList = json_decode($this->ReadPropertyString('HostsList'), true);
            if (!is_array($hostsList)) {
                return;
            }
            $totalState = true;
            foreach ($hostsList as $key => $host) {
                $IdentState = 'lst_' . str_replace('.', '_', $host['IPAddress']);
                $IdentLastSeen = 'lst_' . str_replace('.', '_', $host['IPAddress']) . '_LastSeen';
                $IdentLastOffline = 'lst_' . str_replace('.', '_', $host['IPAddress']) . '_LastOffline';

                $deviceState = $this->pingHost($host['IPAddress'], $IdentState);
                $this->SetValue($IdentState, $deviceState);
                if ($deviceState) {
                    $this->SetValue($IdentLastSeen, time());
                }

                //Wenn ein Gerät offline ist, setze die Gesamt Variable auf false
                if (!$deviceState) {
                    $totalState = false;
                    $this->SetValue($IdentState, $deviceState);
                    $this->SetValue($IdentLastOffline, time());
                    $this->SendDebug('Device offline', $host['IPAddress'], 0);
                }
            }
            $this->SetValue('DeviceStatus', $totalState);
            if ($totalState) {
                $this->SetValue('LastSeen', time());
            }
            return;
        }

        //Nur den einen Host pingen
        $deviceState = $this->pingHost($this->ReadPropertyString('IPAddress'), 'DeviceStatus');
        $this->SetValue('DeviceStatus', $deviceState);
        if ($deviceState) {
            $this->SetValue('LastSeen', time());
        } else {
            $this->SetValue('LastOffline', time());
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->SendDebug(__FUNCTION__ . ' Ident', $Ident, 0);
        $this->SendDebug(__FUNCTION__ . ' Value', $Value, 0);
        switch ($Ident) {
            case 'DeviceWOL':
                $this->SendDebug(__FUNCTION__, 'Device wakeup', 0);
                $this->WakeOnLan();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Undefined Ident', 0);
                break;
        }
    }

    public function listOfHostsActive(bool $Value): void
    {
        if ($Value) {
            $this->UpdateFormField('HostsList', 'visible', true);
            $this->UpdateFormField('IPAddress', 'visible', false);
            $this->UpdateFormField('WOL', 'visible', false);
        } else {
            $this->UpdateFormField('HostsList', 'visible', false);
            $this->UpdateFormField('IPAddress', 'visible', true);
            $this->UpdateFormField('WOL', 'visible', true);
        }
    }

    private function pingHost(string $IPAddress, string $Ident): bool
    {
        if ($IPAddress == '' || $this->ReadPropertyInteger('PingTimeout') <= 0) {
            return false;
        }

        if (@Sys_Ping($IPAddress, $this->ReadPropertyInteger('PingTimeout'))) {
            $this->SetBuffer('Tries' . $Ident, '0');
            $this->SetBuffer($Ident, 'true');
            return true;
        }

        if ((intval($this->GetBuffer('Tries' . $Ident)) < $this->ReadPropertyInteger('Tries')) && ($this->ReadPropertyBoolean('ActiveTries'))) {
            $tries = intval($this->GetBuffer('Tries' . $Ident));
            $tries++;
            $this->SendDebug('UpdateStatus :: Tries for IP-Address', $IPAddress, 0);
            $this->SendDebug('UpdateStatus :: Tries' . $Ident, $tries, 0);
            $this->SetBuffer('Tries' . $Ident, strval($tries));
        }
        if (intval($this->GetBuffer('Tries' . $Ident)) >= $this->ReadPropertyInteger('Tries')) {
            $this->SetBuffer('Tries' . $Ident, '0');
            $this->SetBuffer($Ident, 'false');
            return false;
        }
        return ($this->GetBuffer($Ident) == 'true') && $this->ReadPropertyBoolean('ActiveTries');
    }

    private function RegisterVariablenProfiles(): void
    {
        //Profile for Online / Offline Status
        if (!IPS_VariableProfileExists('DM.Status')) {
            $this->RegisterProfileBooleanEx('DM.Status', 'Network', '', '', [
                [false, 'Offline',  '', 0xFF0000],
                [true, 'Online',  '', 0x00FF00],
            ]);
        }

        //Profile for WOL
        if (!IPS_VariableProfileExists('DM.WOL')) {
            $Associations = [];
            $Associations[] = [1, 'Start', '', -1];
            $this->RegisterProfileIntegerEx('DM.WOL', 'Network', '', '', $Associations);
        }
    }
}
