<?php

declare(strict_types=1);

define('VAR_BOOL', 0);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

use PHPUnit\Framework\TestCase;

class TelefonketteParallelTest extends TestCase
{
    protected $VoIPID;
    protected $TelefonketteID;

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

        parent::setUp();
    }

    public function testConnections()
    {
        $instanceID = $this->TelefonketteID;
        $triggerID = $this->CreateActionVariable(VAR_BOOL);
        $configuration = json_encode(
            [
                'Trigger'      => $triggerID,
                'VoIP'         => $this->VoIPID,
                'PhoneNumbers' => json_encode(
                    [
                        [
                            'PhoneNumber' => '132168'
                        ]
                    ]),
                'MaxSyncCallCount' => 1,
                'CallDuraion'      => 15,
                'ConfirmKey'       => '1'
            ]
                        );
        IPS_SetConfiguration($instanceID, $configuration);
        IPS_ApplyChanges($instanceID);

        //3 numbers 2 syncCalls - 0 confirms
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 12:00:00'));
        TK_UpdateCalls($instanceID);
        $foreignCall = VOIP_Connect($this->VoIPID, '424242');
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(2, count($activeConnections));
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(1, count($activeConnections));
        $this->assertEquals($foreignCall, $activeConnections[$foreignCall]['ID']);
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