<?php

declare(strict_types=1);

define('VAR_BOOL', 0);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

use PHPUnit\Framework\TestCase;

class TelefonketteConfigurationTest extends TestCase
{
    protected $VoIPID;
    protected $TelefonketteID;
    protected $TriggerID;

    protected function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our core stubs for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        $this->TelefonketteID = IPS_CreateInstance('{58F82675-99C8-5CA1-8333-68CDAD6EBE6E}');
        $this->VoIPID = IPS_CreateInstance('{A4224A63-49EA-445F-8422-22EF99D8F624}');
        $this->TriggerID = $this->CreateActionVariable(VAR_BOOL);

        parent::setUp();
    }

    public function testListEmpty()
    {
        $instanceID = $this->TelefonketteID;
        $configuration = json_encode(
            [
                'Trigger'          => $this->TriggerID,
                'VoIP'             => $this->VoIPID,
                'PhoneNumbers'     => json_encode([]),
                'MaxSyncCallCount' => 2,
                'CallDuraion'      => 15,
                'ConfirmKey'       => '1'
            ]
                        );
        IPS_SetConfiguration($instanceID, $configuration);
        IPS_ApplyChanges($instanceID);
        $status = IPS\InstanceManager::getInstance($instanceID)['InstanceStatus'];
        $this->assertEquals(104, $status);
        TK_UpdateCalls($instanceID);
        $this->assertEquals(0, count(VOIP_StubsGetConnections($this->VoIPID)));
    }

    public function testNoVoip()
    {
        $instanceID = $this->TelefonketteID;
        $configuration = json_encode(
            [
                'Trigger'      => $this->TriggerID,
                'VoIP'         => 0,
                'PhoneNumbers' => json_encode([[
                    'PhoneNumber' => '111111'
                ],
                [
                    'PhoneNumber' => '222222'
                ]]
                ),
                'MaxSyncCallCount' => 2,
                'CallDuraion'      => 15,
                'ConfirmKey'       => '1'
            ]
                        );
        IPS_SetConfiguration($instanceID, $configuration);
        IPS_ApplyChanges($instanceID);
        $status = IPS\InstanceManager::getInstance($instanceID)['InstanceStatus'];
        $this->assertEquals(104, $status);
        TK_UpdateCalls($instanceID);
        $this->assertEquals(0, count(VOIP_StubsGetConnections($this->VoIPID)));
    }

    protected function CreateActionVariable(int $VariableType)
    {
        $variableID = IPS_CreateVariable($VariableType);
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        IPS_SetVariableCustomAction($variableID, $scriptID);
        return $variableID;
    }
}