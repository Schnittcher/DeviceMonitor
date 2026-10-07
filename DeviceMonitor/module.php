<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/WOLHelper.php';

class DeviceMonitor extends IPSModuleStrict
{
    use WOLHelper;

    private const PRESENTATION_VALUE = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
    private const PRESENTATION_DATE_TIME = '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}';
    private const PRESENTATION_ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
    private const PRESENTATION_LEGACY = '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}';
    //Gesamtstatus der Liste: online, wenn alle Geräte online sind bzw. wenn mindestens ein Gerät online ist
    private const LIST_STATUS_ALL = 0;
    private const LIST_STATUS_ANY = 1;
    private const DEFAULT_ICON = 'network-wired';
    //Größe des hochgeladenen Kachelbilds als Base64-Text (etwa 1,5 MB Bilddaten)
    private const MAX_TILE_IMAGE_LENGTH = 2097152;
    //Profile früherer Versionen, deren Variablen auf die neuen Darstellungen migriert werden
    private const LEGACY_PROFILES = ['DM.Status', 'DM.WOL', '~UnixTimestamp'];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('ListOfHosts', false);
        $this->RegisterPropertyString('IPAddress', '');
        $this->RegisterPropertyString('HostName', '');
        $this->RegisterPropertyString('HostsList', '[]');
        $this->RegisterPropertyString('BroadcastAddress', '');
        $this->RegisterPropertyString('MACAddress', '');
        $this->RegisterPropertyBoolean('ActiveTries', false);
        $this->RegisterPropertyInteger('Tries', 20);
        $this->RegisterPropertyInteger('PingTimeout', 1000);
        $this->RegisterPropertyInteger('Interval', 20);
        $this->RegisterPropertyBoolean('WakeOnLan', false);
        $this->RegisterPropertyInteger('ListStatusMode', self::LIST_STATUS_ALL);
        //Kachel: Icon ist immer gesetzt, das Bild ist optional (hochgeladen, Base64) und ersetzt bei der Kachel für ein Gerät das Icon
        $this->RegisterPropertyString('TileIcon', self::DEFAULT_ICON);
        $this->RegisterPropertyString('TileImageFile', '');
        $this->RegisterPropertyBoolean('TileShowName', true);
        $this->RegisterPropertyBoolean('TileShowIP', false);

        //Nächste freie ID für Zeilen der Geräteliste, wird nie zurückgesetzt, damit keine ID wiederverwendet wird
        $this->RegisterAttributeInteger('NextHostID', 1);

        //HTML-Kachel (module.html)
        $this->SetVisualizationType(1);

        $this->RegisterTimer('DM_UpdateTimer', 0, 'DM_UpdateStatus($_IPS[\'TARGET\']);');

        $this->RegisterVariableBoolean('DeviceStatus', $this->Translate('State'), $this->StatusPresentation(), 0);
        $this->RegisterVariableInteger('LastSeen', $this->Translate('Last seen'), $this->DateTimePresentation(), 1);
        $this->RegisterVariableInteger('LastOffline', $this->Translate('Last offline'), $this->DateTimePresentation(), 1);

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

        $this->MigrateLegacyPresentations();

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

        //Jede Zeile der Liste braucht eine feste ID. Fehlt sie, wird sie vergeben (inklusive Migration der alten Idents)
        //und die Konfiguration per einmaligem Timer erneut angewendet (ein direkter Aufruf wäre ein re-entranter Aufruf).
        $nextHostID = 1;
        if ($hostsListValid && $this->AssignHostIDs($hostsList, $nextHostID)) {
            IPS_SetProperty($this->InstanceID, 'HostsList', json_encode($hostsList));
            $this->WriteAttributeInteger('NextHostID', $nextHostID);
            $this->RegisterOnceTimer('DM_ApplyHostIDs', 'IPS_ApplyChanges($_IPS[\'TARGET\']);');
            return;
        }

        //Idents aller Variablen, die zur aktuellen Liste gehören
        $validIdents = [];
        foreach ($hostsList as $host) {
            [$identBase, $identSeen, $identOffline, $identWOL] = $this->HostIdents($host);
            $validIdents[] = $identBase;
            $validIdents[] = $identSeen;
            $validIdents[] = $identOffline;
            //Die Wake-on-Lan-Variable wird unten über MaintainVariable angelegt oder entfernt
            if ($this->HostSupportsWakeOnLan($host)) {
                $validIdents[] = $identWOL;
            }
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
            [$IdentState, $IdentLastSeen, $IdentLastOffline, $IdentWOL] = $this->HostIdents($host);

            $variablePosition++;
            $this->MaintainVariable($IdentState, $this->Translate('State') . ' ' . $host['name'], 0, $this->StatusPresentation(), $variablePosition, $ListOfHosts);
            if ($ListOfHosts) {
                //Buffer resetten, damit die Zählung neu beginnen kann.
                $this->SetBuffer($IdentState, '');
                $this->SetBuffer('Tries' . $IdentState, '0');
            }
            $variablePosition++;
            $this->MaintainVariable($IdentLastSeen, $this->Translate('Last seen') . ' ' . $host['name'], 1, $this->DateTimePresentation(), $variablePosition, $ListOfHosts);
            $variablePosition++;
            $this->MaintainVariable($IdentLastOffline, $this->Translate('Last offline') . ' ' . $host['name'], 1, $this->DateTimePresentation(), $variablePosition, $ListOfHosts);
            //Wake on Lan je Gerät, nur mit aktivem Wake on Lan und gültiger MAC-Adresse der Zeile
            $variablePosition++;
            $hostWOL = $ListOfHosts && $this->HostSupportsWakeOnLan($host);
            $this->MaintainVariable($IdentWOL, $this->Translate('Wake On Lan') . ' ' . $host['name'], 1, $this->WOLPresentation(), $variablePosition, $hostWOL);
            if ($hostWOL) {
                $this->SetValue($IdentWOL, 1);
                $this->EnableAction($IdentWOL);
            }
        }

        $WOL = $this->ReadPropertyBoolean('WakeOnLan');
        $this->MaintainVariable('DeviceWOL', $this->Translate('Wake On Lan'), 1, $this->WOLPresentation(), 0, $this->ReadPropertyBoolean('WakeOnLan') == true && $this->ReadPropertyBoolean('ListOfHosts') == false);
        if ($this->ReadPropertyBoolean('WakeOnLan') && $this->ReadPropertyBoolean('ListOfHosts') == false) {
            $this->SetValue('DeviceWOL', 1);
            $this->EnableAction('DeviceWOL');
        }

        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetTimerInterval('DM_UpdateTimer', 0);
            $this->SetStatus(104);
            $this->UpdateTile(true);
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
            $this->UpdateTile(true);
            return;
        }

        $this->SetTimerInterval('DM_UpdateTimer', $this->ReadPropertyInteger('Interval') * 1000);
        $this->UpdateStatus();
        $this->SetStatus(102);
        //Nach einer Änderung der Konfiguration auch das Bild neu senden
        $this->UpdateTile(true);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->ReadPropertyBoolean('ListOfHosts')) {
            //Bei der Liste entfallen die Felder des einzelnen Geräts, die MAC-Adresse steht in jeder Zeile der Liste
            foreach (['DevicePanel', 'MACAddress', 'TileShowName'] as $name) {
                $this->SetFormElementProperty($form['elements'], $name, 'visible', false);
            }
            $this->SetFormElementProperty($form['elements'], 'DevicesPanel', 'visible', true);
        }
        //Abhängige Felder sind nur bedienbar, wenn ihre Funktion aktiv ist
        $this->SetFormElementProperty($form['elements'], 'Tries', 'enabled', $this->ReadPropertyBoolean('ActiveTries'));
        foreach (['BroadcastAddress', 'MACAddress'] as $name) {
            $this->SetFormElementProperty($form['elements'], $name, 'enabled', $this->ReadPropertyBoolean('WakeOnLan'));
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
                    $this->UpdateTile();
                    break;
                default:
                    # code...
                    break;
            }
        }
    }

    public function GetVisualizationTile(): string
    {
        //Das Modell wird nach dem Laden der Kachel eingesetzt, handleMessage steht erst im HTML
        $initialHandling = '<script>handleMessage(' . json_encode(json_encode($this->BuildTileModel(true))) . ');</script>';
        return file_get_contents(__DIR__ . '/module.html') . $initialHandling;
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
            $allOnline = true;
            $anyOnline = false;
            foreach ($hostsList as $key => $host) {
                [$IdentState, $IdentLastSeen, $IdentLastOffline] = $this->HostIdents($host);

                $deviceState = $this->pingHost($host['IPAddress'], $IdentState);
                $this->SetValue($IdentState, $deviceState);
                if ($deviceState) {
                    $anyOnline = true;
                    $this->SetValue($IdentLastSeen, time());
                }

                if (!$deviceState) {
                    $allOnline = false;
                    $this->SetValue($IdentState, $deviceState);
                    $this->SetValue($IdentLastOffline, time());
                    $this->SendDebug('Device offline', $host['IPAddress'], 0);
                }
            }
            //Gesamtstatus: online, wenn alle Geräte online sind, oder wenn mindestens ein Gerät online ist
            $totalState = $this->ReadPropertyInteger('ListStatusMode') === self::LIST_STATUS_ANY ? $anyOnline : $allOnline;
            $this->SetValue('DeviceStatus', $totalState);
            if ($totalState) {
                $this->SetValue('LastSeen', time());
            }
            $this->UpdateTile();
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
        $this->UpdateTile();
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
                if (str_starts_with($Ident, 'lst_') && str_ends_with($Ident, '_WOL')) {
                    $this->WakeOnLanHost($Ident);
                    break;
                }
                $this->SendDebug(__FUNCTION__, 'Undefined Ident', 0);
                break;
        }
    }

    public function listOfHostsActive(bool $Value): void
    {
        $this->UpdateFormField('DevicesPanel', 'visible', $Value);
        $this->UpdateFormField('DevicePanel', 'visible', !$Value);
        $this->UpdateFormField('MACAddress', 'visible', !$Value);
        $this->UpdateFormField('TileShowName', 'visible', !$Value);
    }

    //Abhängige Felder sind nur bedienbar, wenn Fehlversuche bzw. Wake on Lan aktiv sind
    public function formStateChanged(bool $ActiveTries, bool $WakeOnLan): void
    {
        $this->UpdateFormField('Tries', 'enabled', $ActiveTries);
        $this->UpdateFormField('BroadcastAddress', 'enabled', $WakeOnLan);
        $this->UpdateFormField('MACAddress', 'enabled', $WakeOnLan);
    }

    //Setzt eine Eigenschaft (zum Beispiel "visible" oder "enabled") beim Element mit dem Namen, auch wenn es in einem Panel oder einer Zeile liegt.
    //Das Formular braucht dadurch keine festen Positionen.
    private function SetFormElementProperty(array &$elements, string $name, string $key, bool $value): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $name) {
                $element[$key] = $value;
            }
            if (isset($element['items'])) {
                $this->SetFormElementProperty($element['items'], $name, $key, $value);
            }
        }
        unset($element);
    }

    //Weckt das Gerät der Listenzeile, zu der die Wake-on-Lan-Variable mit dem Ident gehört
    private function WakeOnLanHost(string $Ident): void
    {
        $hostsList = json_decode($this->ReadPropertyString('HostsList'), true);
        if (!is_array($hostsList)) {
            return;
        }
        foreach ($hostsList as $host) {
            if ($this->HostIdents($host)[3] === $Ident && $this->HostSupportsWakeOnLan($host)) {
                $this->SendDebug(__FUNCTION__, 'Device wakeup', 0);
                $this->SendMagicPacket($this->ReadPropertyString('BroadcastAddress'), $host['MACAddress']);
                return;
            }
        }
        $this->SendDebug(__FUNCTION__, 'No device with Wake on Lan found for ' . $Ident, 0);
    }

    //Eine Listenzeile unterstützt Wake on Lan, wenn die Funktion aktiv ist und die Zeile eine gültige MAC-Adresse hat
    private function HostSupportsWakeOnLan(array $host): bool
    {
        return $this->ReadPropertyBoolean('WakeOnLan') && $this->IsValidMACAddress((string) ($host['MACAddress'] ?? ''));
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

    //Schickt das aktuelle Modell an geöffnete Kacheln. Das Bild ist groß und ändert sich nur mit der Konfiguration, deshalb
    //geht es nur beim Laden der Kachel und nach einer Änderung der Konfiguration mit.
    private function UpdateTile(bool $withImage = false): void
    {
        $this->UpdateVisualizationValue(json_encode($this->BuildTileModel($withImage)));
    }

    //Alles, was die Kachel anzeigt. Texte kommen übersetzt aus dem Modul, die Kachel setzt sie nur ein.
    private function BuildTileModel(bool $withImage = false): array
    {
        $model = [
            'icon' => $this->TileIcon($this->ReadPropertyString('TileIcon')),
            't'    => [
                'online'      => $this->Translate('Online'),
                'offline'     => $this->Translate('Offline'),
                'seen'        => $this->Translate('Last seen'),
                'lastOffline' => $this->Translate('Last offline'),
                'wake'        => $this->Translate('Wake up')
            ]
        ];

        if (!$this->ReadPropertyBoolean('ListOfHosts')) {
            $model['mode'] = 'single';
            //Der Hostname ist optional und in der Kachel abschaltbar
            $model['name'] = $this->ReadPropertyBoolean('TileShowName') ? trim($this->ReadPropertyString('HostName')) : '';
            $model['ip'] = $this->ReadPropertyBoolean('TileShowIP') ? trim($this->ReadPropertyString('IPAddress')) : '';
            if ($withImage) {
                $model['image'] = $this->TileImage();
            }
            $model['online'] = $this->TileBoolean('DeviceStatus');
            $model['seen'] = $this->TileFormatted('LastSeen');
            $model['lastOffline'] = $this->TileFormatted('LastOffline');
            $model['wol'] = $this->ReadPropertyBoolean('WakeOnLan') ? 'DeviceWOL' : '';
            return $model;
        }

        $hostsList = json_decode($this->ReadPropertyString('HostsList'), true);
        $items = [];
        $onlineCount = 0;
        foreach (is_array($hostsList) ? $hostsList : [] as $host) {
            [$identState, $identSeen, $identOffline, $identWOL] = $this->HostIdents($host);
            $online = $this->TileBoolean($identState);
            $onlineCount += $online ? 1 : 0;
            $items[] = [
                'name'    => (string) ($host['name'] ?? ''),
                'ip'      => $this->ReadPropertyBoolean('TileShowIP') ? (string) ($host['IPAddress'] ?? '') : '',
                'icon'    => $this->TileIcon((string) ($host['Icon'] ?? '')),
                'online'  => $online,
                'seen'    => $this->TileFormatted($identSeen),
                'wol'     => $this->HostSupportsWakeOnLan($host) ? $identWOL : ''
            ];
        }
        $model['mode'] = 'list';
        $model['online'] = $this->TileBoolean('DeviceStatus');
        $model['summary'] = sprintf($this->Translate('%d of %d online'), $onlineCount, count($items));
        $model['items'] = $items;
        return $model;
    }

    //Icon der Kachel: ein Font-Awesome-Name, fehlt er oder ist er ungültig, gilt der Standard
    private function TileIcon(string $icon): string
    {
        $icon = strtolower(trim($icon));
        return preg_match('/^[a-z0-9-]+$/', $icon) === 1 ? $icon : self::DEFAULT_ICON;
    }

    //Optionales, im Formular hochgeladenes Bild als Daten-URI, leer wenn keins gewählt ist oder das Bild nicht passt
    private function TileImage(): string
    {
        $encoded = $this->ReadPropertyString('TileImageFile');
        if ($encoded === '') {
            return '';
        }
        //Das Bild liegt in der Konfiguration und steckt im HTML der Kachel, deshalb nur bis etwa 1,5 MB
        if (strlen($encoded) > self::MAX_TILE_IMAGE_LENGTH) {
            $this->SendDebug(__FUNCTION__, 'Image is too large: ' . strlen($encoded) . ' > ' . self::MAX_TILE_IMAGE_LENGTH, 0);
            $this->LogMessage($this->Translate('The tile image is too large and is not shown. Please choose a smaller image.'), KL_WARNING);
            return '';
        }
        $content = base64_decode($encoded, true);
        if ($content === false) {
            return '';
        }
        //Der Typ kommt aus dem Inhalt der Datei, nicht aus dem Dateinamen
        $imageInfo = @getimagesizefromstring($content);
        $mimeType = $imageInfo['mime'] ?? '';
        if ($mimeType === '' && stripos($content, '<svg') !== false) {
            $mimeType = 'image/svg+xml';
        }
        if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/svg+xml'], true)) {
            $this->SendDebug(__FUNCTION__, 'Unsupported image type', 0);
            return '';
        }
        return 'data:' . $mimeType . ';base64,' . $encoded;
    }

    private function TileBoolean(string $ident): bool
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        return $variableID !== false && GetValueBoolean($variableID);
    }

    private function TileFormatted(string $ident): string
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        return $variableID === false ? '-' : GetValueFormatted($variableID);
    }

    //Idents der Variablen einer Listenzeile, abgeleitet von der festen Zeilen-ID
    private function HostIdents(array $host): array
    {
        $base = 'lst_' . ($host['ID'] ?? '');
        return [$base, $base . '_LastSeen', $base . '_LastOffline', $base . '_WOL'];
    }

    //Vergibt fehlende, ungültige oder doppelte Zeilen-IDs als fortlaufende Zahl. Vorhandene Variablen mit dem alten, IP-basierten Ident
    //werden auf den neuen Ident umbenannt, damit Werte, Archivdaten und Verknüpfungen erhalten bleiben.
    //Gibt true zurück, wenn sich die Liste geändert hat. $nextID ist danach der neue Zählerstand, den der Aufrufer erst nach dem
    //Speichern der Liste im Attribut ablegt: Ein Wiederanlauf nach einem Abbruch rechnet so mit demselben Zählerstand und vergibt
    //dieselben Nummern, bereits umbenannte Variablen verwaisen nicht.
    private function AssignHostIDs(array &$hostsList, int &$nextID): bool
    {
        //Gültige und eindeutige IDs bleiben, der Zähler liegt immer hinter der höchsten vergebenen ID
        $keep = [];
        $maxID = 0;
        foreach ($hostsList as $index => $host) {
            $id = (string) ($host['ID'] ?? '');
            if (preg_match('/^[1-9][0-9]*$/', $id) === 1 && !in_array($id, $keep, true)) {
                $keep[$index] = $id;
                $maxID = max($maxID, (int) $id);
            }
        }
        $nextID = max($this->ReadAttributeInteger('NextHostID'), $maxID + 1);

        $changed = false;
        $variableIDs = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $variableIDs[IPS_GetObject($childID)['ObjectIdent']] = $childID;
        }
        foreach ($hostsList as $index => &$host) {
            if (isset($keep[$index])) {
                continue;
            }
            $id = (string) $nextID++;
            $changed = true;

            //Nur bei Zeilen ohne ID kann es Variablen mit dem alten Ident geben
            $oldBase = 'lst_' . str_replace('.', '_', $host['IPAddress'] ?? '');
            $host['ID'] = $id;
            if ($oldBase === 'lst_') {
                continue;
            }
            $newIdents = $this->HostIdents($host);
            foreach ([$oldBase, $oldBase . '_LastSeen', $oldBase . '_LastOffline'] as $index => $oldIdent) {
                //Nur umbenennen, wenn die alte Variable existiert und der neue Ident frei ist
                if (!isset($variableIDs[$oldIdent]) || isset($variableIDs[$newIdents[$index]])) {
                    continue;
                }
                $this->SendDebug(__FUNCTION__, 'Rename ' . $oldIdent . ' to ' . $newIdents[$index], 0);
                IPS_SetIdent($variableIDs[$oldIdent], $newIdents[$index]);
            }
        }
        unset($host);
        return $changed;
    }

    private function StatusPresentation(): array
    {
        return [
            'PRESENTATION' => self::PRESENTATION_VALUE,
            'ICON'         => 'network-wired',
            'OPTIONS'      => json_encode([
                ['Value' => false, 'Caption' => $this->Translate('Offline'), 'IconValue' => '', 'IconActive' => false, 'ColorActive' => true, 'ColorValue' => 0xFF0000, 'ContentColorActive' => false],
                ['Value' => true, 'Caption' => $this->Translate('Online'), 'IconValue' => '', 'IconActive' => false, 'ColorActive' => true, 'ColorValue' => 0x00FF00, 'ContentColorActive' => false]
            ])
        ];
    }

    private function DateTimePresentation(): array
    {
        return [
            'PRESENTATION'    => self::PRESENTATION_DATE_TIME,
            'DATE'            => 1,
            'MONTH_TEXT'      => false,
            'DAY_OF_THE_WEEK' => false,
            'TIME'            => 2
        ];
    }

    private function WOLPresentation(): array
    {
        return [
            'PRESENTATION' => self::PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode([
                ['Value' => 1, 'Caption' => $this->Translate('Start'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1]
            ])
        ];
    }

    //Stellt Variablen früherer Versionen (Legacy-Profil DM.Status, DM.WOL, ~UnixTimestamp) auf die nativen Darstellungen um.
    //Nur Variablen mit unserem Legacy-Profil werden angefasst, Ident, Wert und Position bleiben erhalten.
    private function MigrateLegacyPresentations(): void
    {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (!IPS_VariableExists($childID)) {
                continue;
            }
            $object = IPS_GetObject($childID);
            $variable = IPS_GetVariable($childID);
            $presentation = $variable['VariablePresentation'];
            if (($presentation['PRESENTATION'] ?? '') !== self::PRESENTATION_LEGACY
                || !in_array($presentation['PROFILE'] ?? '', self::LEGACY_PROFILES, true)) {
                continue;
            }

            $ident = $object['ObjectIdent'];
            if ($ident === 'DeviceWOL') {
                $newPresentation = $this->WOLPresentation();
            } elseif (str_ends_with($ident, 'LastSeen') || str_ends_with($ident, 'LastOffline')) {
                $newPresentation = $this->DateTimePresentation();
            } elseif ($ident === 'DeviceStatus' || str_starts_with($ident, 'lst_')) {
                $newPresentation = $this->StatusPresentation();
            } else {
                continue;
            }
            $this->SendDebug(__FUNCTION__, 'Migrate ' . $ident . ' from ' . $presentation['PROFILE'], 0);
            $this->MaintainVariable($ident, $object['ObjectName'], $variable['VariableType'], $newPresentation, $object['ObjectPosition'], true);
        }
    }
}
