<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DeviceMonitorTest extends TestCase
{
    private const MODULE_ID = '{7AF78FBA-B705-CC81-E446-86C9135C1C28}';
    private const PRESENTATION_VALUE = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
    private const PRESENTATION_DATE_TIME = '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}';
    private const PRESENTATION_ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';

    protected function setUp(): void
    {
        // Jeder Test startet mit einem leeren Kernel und lädt die Bibliothek neu
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        parent::setUp();
    }

    public function testNewInstanceUsesNativePresentations(): void
    {
        $instanceID = $this->createInstance([]);

        $this->assertSame(self::PRESENTATION_VALUE, $this->presentation($instanceID, 'DeviceStatus')['PRESENTATION']);
        $this->assertSame(self::PRESENTATION_DATE_TIME, $this->presentation($instanceID, 'LastSeen')['PRESENTATION']);
        $this->assertSame(self::PRESENTATION_DATE_TIME, $this->presentation($instanceID, 'LastOffline')['PRESENTATION']);
        // Die eigenen Profile DM.Status und DM.WOL werden nicht mehr angelegt
        $this->assertFalse(IPS_VariableProfileExists('DM.Status'));
        $this->assertFalse(IPS_VariableProfileExists('DM.WOL'));
    }

    public function testStatusPresentationShowsOnlineAndOfflineWithColors(): void
    {
        $instanceID = $this->createInstance([]);

        $options = json_decode($this->presentation($instanceID, 'DeviceStatus')['OPTIONS'], true);

        $this->assertSame([false, true], array_column($options, 'Value'));
        $this->assertSame(['Offline', 'Online'], array_column($options, 'Caption'));
        $this->assertSame([0xFF0000, 0x00FF00], array_column($options, 'ColorValue'));
    }

    public function testInactiveInstanceHasStatusInactive(): void
    {
        $instanceID = $this->createInstance(['Active' => false, 'IPAddress' => '192.0.2.10']);

        $this->assertSame(104, IPS_GetInstance($instanceID)['InstanceStatus']);
        $this->assertFalse(GetValueBoolean($this->variableID($instanceID, 'DeviceStatus')));
    }

    public function testActiveInstanceWithoutIPAddressIsInvalid(): void
    {
        $instanceID = $this->createInstance(['Active' => true, 'IPAddress' => '']);

        $this->assertSame(201, IPS_GetInstance($instanceID)['InstanceStatus']);
    }

    public function testActiveInstanceWithInvalidIntervalOrTimeoutIsInvalid(): void
    {
        $withoutInterval = $this->createInstance(['Active' => true, 'IPAddress' => '192.0.2.10', 'Interval' => 0]);
        $withoutTimeout = $this->createInstance(['Active' => true, 'IPAddress' => '192.0.2.10', 'PingTimeout' => 0]);

        $this->assertSame(201, IPS_GetInstance($withoutInterval)['InstanceStatus']);
        $this->assertSame(201, IPS_GetInstance($withoutTimeout)['InstanceStatus']);
    }

    public function testActiveListWithoutHostsOrWithEmptyIPAddressIsInvalid(): void
    {
        $emptyList = $this->createInstance(['Active' => true, 'ListOfHosts' => true, 'HostsList' => '[]']);
        $emptyIP = $this->createInstance(['Active' => true, 'ListOfHosts' => true, 'HostsList' => $this->hosts([['name' => 'A', 'IPAddress' => '']])]);

        $this->assertSame(201, IPS_GetInstance($emptyList)['InstanceStatus']);
        $this->assertSame(201, IPS_GetInstance($emptyIP)['InstanceStatus']);
    }

    public function testActiveSingleDeviceChecksStatus(): void
    {
        $instanceID = $this->createInstance(['Active' => true, 'IPAddress' => '192.0.2.10']);

        $this->assertSame(102, IPS_GetInstance($instanceID)['InstanceStatus']);
        $this->assertTrue(GetValueBoolean($this->variableID($instanceID, 'DeviceStatus')));
        $this->assertGreaterThan(0, GetValueInteger($this->variableID($instanceID, 'LastSeen')));
    }

    public function testListRowsGetConsecutiveIDsAndKeepThem(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Eins', 'IPAddress' => '192.0.2.1'],
            ['name' => 'Zwei', 'IPAddress' => '192.0.2.2']
        ]));
        $this->applyUntilStable($instanceID);

        $ids = array_column($this->hostsList($instanceID), 'ID');
        $this->assertSame(['1', '2'], $ids);
        $this->assertNotFalse($this->variableID($instanceID, 'lst_1'));
        $this->assertNotFalse($this->variableID($instanceID, 'lst_2_LastSeen'));

        // Wiederholtes Anwenden ändert weder die IDs noch die Variablen
        $variablesBefore = $this->listVariables($instanceID);
        IPS_ApplyChanges($instanceID);
        IPS_ApplyChanges($instanceID);
        $this->assertSame(['1', '2'], array_column($this->hostsList($instanceID), 'ID'));
        $this->assertSame($variablesBefore, $this->listVariables($instanceID));
    }

    public function testChangingIPAddressKeepsVariables(): void
    {
        $instanceID = $this->createInstance($this->listConfig([['name' => 'Eins', 'IPAddress' => '192.0.2.1']]));
        $this->applyUntilStable($instanceID);
        $variablesBefore = $this->listVariables($instanceID);

        $hosts = $this->hostsList($instanceID);
        $hosts[0]['IPAddress'] = '192.0.2.99';
        $hosts[0]['name'] = 'Umbenannt';
        IPS_SetProperty($instanceID, 'HostsList', json_encode($hosts));
        IPS_ApplyChanges($instanceID);

        $this->assertSame($variablesBefore, $this->listVariables($instanceID));
    }

    public function testDeletedIDIsNotAssignedAgain(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Eins', 'IPAddress' => '192.0.2.1'],
            ['name' => 'Zwei', 'IPAddress' => '192.0.2.2']
        ]));
        $this->applyUntilStable($instanceID);

        // Letzte Zeile löschen, neue Zeile hinzufügen: Sie bekommt 3, nicht noch einmal 2
        $hosts = $this->hostsList($instanceID);
        array_pop($hosts);
        IPS_SetProperty($instanceID, 'HostsList', json_encode($hosts));
        IPS_ApplyChanges($instanceID);
        $this->assertFalse($this->variableID($instanceID, 'lst_2'));

        $hosts[] = ['name' => 'Drei', 'IPAddress' => '192.0.2.3'];
        IPS_SetProperty($instanceID, 'HostsList', json_encode($hosts));
        $this->applyUntilStable($instanceID);

        $this->assertSame(['1', '3'], array_column($this->hostsList($instanceID), 'ID'));
        $this->assertNotFalse($this->variableID($instanceID, 'lst_3'));
        $this->assertFalse($this->variableID($instanceID, 'lst_2'));
    }

    public function testDuplicateIDsAreReplaced(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Eins', 'IPAddress' => '192.0.2.1', 'ID' => '1'],
            ['name' => 'Kopie', 'IPAddress' => '192.0.2.2', 'ID' => '1']
        ]));
        $this->applyUntilStable($instanceID);

        $this->assertSame(['1', '2'], array_column($this->hostsList($instanceID), 'ID'));
    }

    public function testOldIPBasedIdentsAreRenamedKeepingTheVariables(): void
    {
        $instanceID = $this->createInstance([]);
        // Zustand einer Instanz vor Version 2.0: Variablen mit dem Ident lst_<IP>
        $old = [];
        foreach (['lst_192_0_2_1' => 0, 'lst_192_0_2_1_LastSeen' => 1, 'lst_192_0_2_1_LastOffline' => 1] as $ident => $type) {
            $variableID = IPS_CreateVariable($type);
            IPS_SetParent($variableID, $instanceID);
            IPS_SetIdent($variableID, $ident);
            $old[$ident] = $variableID;
        }

        IPS_SetConfiguration($instanceID, json_encode($this->listConfig([['name' => 'Eins', 'IPAddress' => '192.0.2.1']])));
        $this->applyUntilStable($instanceID);

        $this->assertSame($old['lst_192_0_2_1'], $this->variableID($instanceID, 'lst_1'));
        $this->assertSame($old['lst_192_0_2_1_LastSeen'], $this->variableID($instanceID, 'lst_1_LastSeen'));
        $this->assertSame($old['lst_192_0_2_1_LastOffline'], $this->variableID($instanceID, 'lst_1_LastOffline'));
        $this->assertFalse($this->variableID($instanceID, 'lst_192_0_2_1'));
    }

    public function testRestartAfterAbortKeepsAlreadyRenamedVariables(): void
    {
        $instanceID = $this->createInstance($this->listConfig([['name' => 'Eins', 'IPAddress' => '192.0.2.1']]));
        $this->applyUntilStable($instanceID);
        $variableID = $this->variableID($instanceID, 'lst_1');

        // Abbruch nach dem Umbenennen, vor dem Speichern der Liste: Die Variablen heißen schon lst_1, die Liste hat aber noch keine ID
        // und der Zähler wurde noch nicht fortgeschrieben, ein Wiederanlauf rechnet also mit demselben Zählerstand.
        $module = IPS\InstanceManager::getInstanceInterface($instanceID);
        (new ReflectionMethod($module, 'WriteAttributeInteger'))->invoke($module, 'NextHostID', 1);
        IPS_SetProperty($instanceID, 'HostsList', $this->hosts([['name' => 'Eins', 'IPAddress' => '192.0.2.1']]));
        $this->applyUntilStable($instanceID);

        $this->assertSame($variableID, $this->variableID($instanceID, 'lst_1'));
        $this->assertSame(['1'], array_column($this->hostsList($instanceID), 'ID'));
    }

    public function testListModeRemovesVariablesOfDeletedRows(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Eins', 'IPAddress' => '192.0.2.1'],
            ['name' => 'Zwei', 'IPAddress' => '192.0.2.2']
        ]));
        $this->applyUntilStable($instanceID);

        $hosts = $this->hostsList($instanceID);
        array_shift($hosts);
        IPS_SetProperty($instanceID, 'HostsList', json_encode($hosts));
        IPS_ApplyChanges($instanceID);

        $this->assertFalse($this->variableID($instanceID, 'lst_1'));
        $this->assertNotFalse($this->variableID($instanceID, 'lst_2'));
    }

    public function testWakeOnLanVariableOnlyForRowsWithValidMACAddress(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Mit', 'IPAddress' => '192.0.2.1', 'MACAddress' => 'AA:BB:CC:DD:EE:01'],
            ['name' => 'Ohne', 'IPAddress' => '192.0.2.2', 'MACAddress' => ''],
            ['name' => 'Falsch', 'IPAddress' => '192.0.2.3', 'MACAddress' => 'zz']
        ], ['WakeOnLan' => true, 'BroadcastAddress' => '192.0.2.255']));
        $this->applyUntilStable($instanceID);

        $this->assertNotFalse($this->variableID($instanceID, 'lst_1_WOL'));
        $this->assertFalse($this->variableID($instanceID, 'lst_2_WOL'));
        $this->assertFalse($this->variableID($instanceID, 'lst_3_WOL'));
        $this->assertSame(self::PRESENTATION_ENUMERATION, $this->presentation($instanceID, 'lst_1_WOL')['PRESENTATION']);
        $this->assertSame($instanceID, IPS_GetVariable($this->variableID($instanceID, 'lst_1_WOL'))['VariableAction']);
    }

    public function testWakeOnLanVariablesDisappearWithoutWakeOnLan(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Mit', 'IPAddress' => '192.0.2.1', 'MACAddress' => 'AA:BB:CC:DD:EE:01']
        ], ['WakeOnLan' => true]));
        $this->applyUntilStable($instanceID);
        $this->assertNotFalse($this->variableID($instanceID, 'lst_1_WOL'));

        IPS_SetProperty($instanceID, 'WakeOnLan', false);
        IPS_ApplyChanges($instanceID);

        $this->assertFalse($this->variableID($instanceID, 'lst_1_WOL'));
    }

    public function testSingleDeviceWakeOnLanVariable(): void
    {
        $withWakeOnLan = $this->createInstance(['IPAddress' => '192.0.2.10', 'WakeOnLan' => true]);
        $without = $this->createInstance(['IPAddress' => '192.0.2.10', 'WakeOnLan' => false]);

        $this->assertNotFalse($this->variableID($withWakeOnLan, 'DeviceWOL'));
        $this->assertFalse($this->variableID($without, 'DeviceWOL'));
    }

    public function testFormShowsTheSingleDevicePanelByDefault(): void
    {
        $instanceID = $this->createInstance([]);

        $form = json_decode(IPS_GetConfigurationForm($instanceID), true);

        $this->assertTrue($this->formElement($form['elements'], 'DevicePanel')['visible'] ?? true);
        $this->assertFalse($this->formElement($form['elements'], 'DevicesPanel')['visible']);
    }

    public function testFormShowsTheListPanelForAListOfHosts(): void
    {
        $instanceID = $this->createInstance(['ListOfHosts' => true]);

        $form = json_decode(IPS_GetConfigurationForm($instanceID), true);

        $this->assertFalse($this->formElement($form['elements'], 'DevicePanel')['visible']);
        $this->assertTrue($this->formElement($form['elements'], 'DevicesPanel')['visible']);
        $this->assertFalse($this->formElement($form['elements'], 'MACAddress')['visible']);
        $this->assertFalse($this->formElement($form['elements'], 'TileShowName')['visible']);
    }

    public function testFormEnablesDependentFieldsOnlyWithTheirFunction(): void
    {
        $off = json_decode(IPS_GetConfigurationForm($this->createInstance([])), true);
        $on = json_decode(IPS_GetConfigurationForm($this->createInstance(['ActiveTries' => true, 'WakeOnLan' => true])), true);

        foreach (['Tries', 'BroadcastAddress', 'MACAddress'] as $name) {
            $this->assertFalse($this->formElement($off['elements'], $name)['enabled'], $name . ' ohne Funktion');
            $this->assertTrue($this->formElement($on['elements'], $name)['enabled'], $name . ' mit Funktion');
        }
    }

    public function testFormAllowsAnEmptyMACAddress(): void
    {
        $form = json_decode(IPS_GetConfigurationForm($this->createInstance([])), true);

        $validate = $this->formElement($form['elements'], 'MACAddress')['validate'];

        $this->assertSame(1, preg_match('/' . $validate . '/', ''));
        $this->assertSame(1, preg_match('/' . $validate . '/', 'AA:BB:CC:DD:EE:FF'));
        $this->assertSame(0, preg_match('/' . $validate . '/', 'AA:BB:CC'));
    }

    public function testTileOfASingleDevice(): void
    {
        $instanceID = $this->createInstance([
            'Active'    => true,
            'IPAddress' => '192.0.2.10',
            'HostName'  => 'Mein Rechner',
            'TileIcon'  => 'tv',
            'WakeOnLan' => true
        ]);

        $model = $this->tileModel($instanceID);

        $this->assertSame('single', $model['mode']);
        $this->assertSame('Mein Rechner', $model['name']);
        $this->assertSame('tv', $model['icon']);
        $this->assertTrue($model['online']);
        $this->assertSame('DeviceWOL', $model['wol']);
        $this->assertSame('', $model['ip']);
    }

    public function testTileHidesHostNameAndShowsIPAddressWhenConfigured(): void
    {
        $instanceID = $this->createInstance([
            'IPAddress'    => '192.0.2.10',
            'HostName'     => 'Mein Rechner',
            'TileShowName' => false,
            'TileShowIP'   => true
        ]);

        $model = $this->tileModel($instanceID);

        $this->assertSame('', $model['name']);
        $this->assertSame('192.0.2.10', $model['ip']);
    }

    public function testTileIconFallsBackToTheDefaultIcon(): void
    {
        $empty = $this->tileModel($this->createInstance(['TileIcon' => '']));
        $invalid = $this->tileModel($this->createInstance(['TileIcon' => '"><script>']));

        $this->assertSame('network-wired', $empty['icon']);
        $this->assertSame('network-wired', $invalid['icon']);
    }

    public function testTileOfAListShowsRowsWithIconAndCount(): void
    {
        $instanceID = $this->createInstance($this->listConfig([
            ['name' => 'Eins', 'IPAddress' => '192.0.2.1', 'Icon' => 'server', 'MACAddress' => 'AA:BB:CC:DD:EE:01'],
            ['name' => 'Zwei', 'IPAddress' => '192.0.2.2', 'Icon' => '']
        ], ['Active' => true, 'WakeOnLan' => true, 'TileShowIP' => true]));
        $this->applyUntilStable($instanceID);

        $model = $this->tileModel($instanceID);

        $this->assertSame('list', $model['mode']);
        $this->assertSame(['Eins', 'Zwei'], array_column($model['items'], 'name'));
        $this->assertSame(['server', 'network-wired'], array_column($model['items'], 'icon'));
        $this->assertSame(['192.0.2.1', '192.0.2.2'], array_column($model['items'], 'ip'));
        $this->assertSame(['lst_1_WOL', ''], array_column($model['items'], 'wol'));
        // Die Stubs übersetzen nicht, der Text kommt im englischen Original (locale.json: "%d von %d online")
        $this->assertSame('2 of 2 online', $model['summary']);
    }

    public function testTileTextUsesTheNameOfTheListEntryAsText(): void
    {
        $instanceID = $this->createInstance($this->listConfig([['name' => '<b>x</b>', 'IPAddress' => '192.0.2.1']]));
        $this->applyUntilStable($instanceID);

        $model = $this->tileModel($instanceID);

        // Der Name bleibt ein Text, das Modul schreibt ihn unverändert ins Modell und die Kachel setzt ihn als Text ein
        $this->assertSame('<b>x</b>', $model['items'][0]['name']);
        $this->assertStringContainsString('textContent', file_get_contents(__DIR__ . '/../DeviceMonitor/module.html'));
        $this->assertStringNotContainsString('innerHTML', file_get_contents(__DIR__ . '/../DeviceMonitor/module.html'));
    }

    public function testTileImageFromUploadedFile(): void
    {
        $png = base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $model = $this->tileModel($this->createInstance(['TileImageFile' => $png]), true);

        $this->assertStringStartsWith('data:image/png;base64,', $model['image']);
    }

    public function testTileImageRejectsUnsupportedOrTooLargeFiles(): void
    {
        $text = $this->tileModel($this->createInstance(['TileImageFile' => base64_encode('das ist kein Bild')]), true);
        $tooLarge = $this->tileModel($this->createInstance(['TileImageFile' => base64_encode(str_repeat('A', 2000000))]), true);
        $none = $this->tileModel($this->createInstance(['TileImageFile' => '']), true);

        $this->assertSame('', $text['image']);
        $this->assertSame('', $tooLarge['image']);
        $this->assertSame('', $none['image']);
    }

    public function testTileImageIsOnlySentOnRequest(): void
    {
        $instanceID = $this->createInstance(['TileImageFile' => base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))]);

        $this->assertArrayNotHasKey('image', $this->tileModel($instanceID, false));
        $this->assertArrayHasKey('image', $this->tileModel($instanceID, true));
    }

    public function testNewInstanceIsAnHTMLTile(): void
    {
        $instanceID = $this->createInstance([]);

        // IPS_GetInstance der Stubs kennt den Visualisierungstyp nicht, er steht im Stub-Modul hinter der Instanz
        $module = IPS\InstanceManager::getInstanceInterface($instanceID);
        $stubModule = (new ReflectionProperty(IPSModuleStrict::class, 'module'))->getValue($module);
        $this->assertSame(1, (new ReflectionProperty(IPSModule::class, 'visualizationType'))->getValue($stubModule));
    }

    private function createInstance(array $configuration): int
    {
        $instanceID = IPS_CreateInstance(self::MODULE_ID);
        IPS_SetConfiguration($instanceID, json_encode($configuration));
        IPS_ApplyChanges($instanceID);
        return $instanceID;
    }

    private function listConfig(array $hosts, array $additional = []): array
    {
        return array_merge(['ListOfHosts' => true, 'HostsList' => $this->hosts($hosts)], $additional);
    }

    private function hosts(array $hosts): string
    {
        return json_encode($hosts);
    }

    // Die Instanz vergibt fehlende IDs und lässt die Konfiguration per einmaligem Timer erneut anwenden. Der Test übernimmt diesen Schritt.
    private function applyUntilStable(int $instanceID): void
    {
        for ($i = 0; $i < 3; $i++) {
            IPS_ApplyChanges($instanceID);
        }
    }

    private function hostsList(int $instanceID): array
    {
        return json_decode(IPS_GetProperty($instanceID, 'HostsList'), true);
    }

    private function variableID(int $instanceID, string $ident): int|false
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $instanceID);
        return $variableID === false ? false : $variableID;
    }

    private function listVariables(int $instanceID): array
    {
        $variables = [];
        foreach (IPS_GetChildrenIDs($instanceID) as $childID) {
            $ident = IPS_GetObject($childID)['ObjectIdent'];
            if (str_starts_with($ident, 'lst_')) {
                $variables[$ident] = $childID;
            }
        }
        ksort($variables);
        return $variables;
    }

    private function presentation(int $instanceID, string $ident): array
    {
        return IPS_GetVariable($this->variableID($instanceID, $ident))['VariablePresentation'];
    }

    private function formElement(array $elements, string $name): array
    {
        foreach ($elements as $element) {
            if (($element['name'] ?? '') === $name) {
                return $element;
            }
            if (isset($element['items'])) {
                $found = $this->formElement($element['items'], $name);
                if ($found !== []) {
                    return $found;
                }
            }
        }
        return [];
    }

    private function tileModel(int $instanceID, bool $withImage = true): array
    {
        $module = IPS\InstanceManager::getInstanceInterface($instanceID);
        $html = $module->GetVisualizationTile();
        $this->assertStringContainsString('handleMessage(', $html);
        preg_match('/<script>handleMessage\((".*")\);<\/script>$/s', $html, $matches);
        $model = json_decode(json_decode($matches[1]), true);
        if (!$withImage) {
            // Ohne Bild: das Modell, das bei den laufenden Aktualisierungen verschickt wird
            $method = new ReflectionMethod($module, 'BuildTileModel');
            return $method->invoke($module, false);
        }
        return $model;
    }
}
