<?php

declare(strict_types=1);

define('VAR_BOOL', 0);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class TelefonketteBaseTest extends TestCase
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

        $this->VoIPID = IPS_CreateInstance('{A4224A63-49EA-445F-8422-22EF99D8F624}');
        $this->TelefonketteID = IPS_CreateInstance('{58F82675-99C8-5CA1-8333-68CDAD6EBE6E}');

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
                        ],
                        [
                            'PhoneNumber' => '123456'
                        ],
                        [
                            'PhoneNumber' => '654321'
                        ]
                    ]),
                'MaxSyncCallCount' => 2,
                'CallDuraion'      => 15,
                'ConfirmKey'      => '1'
            ]
                        );
        IPS_SetConfiguration($instanceID, $configuration);
        IPS_ApplyChanges($instanceID);

        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 12:00:00'));
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '132168');
        $this->assertEquals($activeConnections[1]['Number'], '123456');
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        print_r(VOIP_StubsGetConnections($this->VoIPID));
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 0);
        //VoIP_StubsAnswerOutgoingCall($this->VoIPID, 0);
        $this->assertTrue(true);
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