<?php

declare(strict_types=1);

define('VAR_BOOL', 0);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

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
                'ConfirmKey'       => '1'
            ]
                        );
        IPS_SetConfiguration($instanceID, $configuration);
        IPS_ApplyChanges($instanceID);
        
        //3 numbers 2 syncCalls - 0 confirms
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 12:00:00'));
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '132168');
        $this->assertEquals($activeConnections[1]['Number'], '123456');
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 0);
        
        //Call untill one number moves up
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:00'));
        TK_UpdateCalls($instanceID);
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:10'));
        TK_UpdateCalls($instanceID);
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:16'));
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[3]['Number'], '123456');
        $this->assertEquals($activeConnections[4]['Number'], '654321');

        //Reset
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [3, 'DTMF', '1']);
        
        //Answer call and keep connection
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:00'));
        TK_UpdateCalls($instanceID);
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:10'));
        TK_UpdateCalls($instanceID);
        VoIP_StubsAnswerOutgoingCall($this->VoIPID, 5);
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:30'));
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        print_r($activeConnections[5]['Number']);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[5]['Number'], '132168');
        $this->assertTrue($activeConnections[5]['Connected']);
        $this->assertEquals($activeConnections[7]['Number'], '654321');
        $this->assertFalse($activeConnections[7]['Connected']);
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