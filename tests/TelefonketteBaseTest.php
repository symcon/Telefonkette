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

        $configuration = json_encode(
            [
                'Trigger'      => $this->TriggerID,
                'VoIP'         => $this->VoIPID,
                'PhoneNumbers' => json_encode(
                    [
                        [
                            'PhoneNumber' => '111111'
                        ],
                        [
                            'PhoneNumber' => '222222'
                        ],
                        [
                            'PhoneNumber' => '333333'
                        ]
                    ]),
                'MaxSyncCallCount'  => 2,
                'CallDuration'      => 15,
                'ConfirmKey'        => '1'
            ]
                        );
        IPS_SetConfiguration($this->TelefonketteID, $configuration);
        IPS_ApplyChanges($this->TelefonketteID);
        TK_setTime($this->TelefonketteID, strtotime('September 1 2020 13:00:00'));

        parent::setUp();
    }

    public function testCallNoAnswer()
    {
        //Call untill one number moves up
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:10'));
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:16'));
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[1]['Number'], '222222');
        $this->assertEquals($activeConnections[2]['Number'], '333333');
    }

    public function testCallConfirm()
    {
        //3 numbers 2 syncCalls - 0 confirms
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '111111');
        $this->assertEquals($activeConnections[1]['Number'], '222222');
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        $this->assertEquals('111111', GetValue(IPS_GetObjectIDByIdent('ConfirmNumber', $instanceID)));
        $this->assertEquals(-1, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 0);
    }

    public function testCallAnswerKeep()
    {
        //Answer call and keep connection
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:10'));
        TK_UpdateCalls($instanceID);
        VoIP_StubsAnswerOutgoingCall($this->VoIPID, 0);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:30'));
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '111111');
        $this->assertTrue($activeConnections[0]['Connected']);
        $this->assertEquals($activeConnections[2]['Number'], '333333');
        $this->assertFalse($activeConnections[2]['Connected']);
    }

    public function testCallAfterConfirm()
    {
        //Call after previous call was confirmed
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '111111');
        $this->assertEquals($activeConnections[1]['Number'], '222222');
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        $this->assertEquals('111111', GetValue(IPS_GetObjectIDByIdent('ConfirmNumber', $instanceID)));
        $this->assertEquals(-1, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 0);
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->TriggerID, VM_UPDATE, [true]);
        $this->assertEquals(-1, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 0);
    }

    public function testCallAnswerDisconnect()
    {
        //Call after previous call was confirmed
        $instanceID = $this->TelefonketteID;
        IPS_SetProperty($instanceID, 'MaxSyncCallCount', 1);
        IPS_ApplyChanges($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $this->assertEquals(VOIP_StubsGetConnections($this->VoIPID)[0]['Number'], '111111');
        VoIP_StubsAnswerOutgoingCall($this->VoIPID, 0);
        $this->assertTrue(VOIP_StubsGetConnections($this->VoIPID)[0]['Connected']);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:30'));
        TK_UpdateCalls($instanceID);
        $this->assertTrue(VOIP_StubsGetConnections($this->VoIPID)[0]['Connected']);
        VoIP_StubsRejectOutgoingCall($this->VoIPID, 0);
        TK_UpdateCalls($instanceID);
        $this->assertEquals(1, count(VOIP_StubsGetConnections($this->VoIPID)));
        $this->assertEquals('222222', VOIP_StubsGetConnections($this->VoIPID)[1]['Number']);
    }

    public function testCallSyncGreaterNumber()
    {
        //Call after previous call was confirmed
        $instanceID = $this->TelefonketteID;
        IPS_SetProperty($instanceID, 'MaxSyncCallCount', 5);
        IPS_ApplyChanges($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $this->assertEquals(3, count(VOIP_StubsGetConnections($this->VoIPID)));
    }

    public function testCallAfterConfirmReset()
    {
        //Call after previous call was confirmed
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        TK_UpdateCalls($instanceID);
        $activeConnections = VOIP_StubsGetConnections($this->VoIPID);
        $this->assertEquals(count($activeConnections), 2);
        $this->assertEquals($activeConnections[0]['Number'], '111111');
        $this->assertEquals($activeConnections[1]['Number'], '222222');
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->VoIPID, 21000, [0, 'DTMF', '1']);
        $this->assertEquals('111111', GetValue(IPS_GetObjectIDByIdent('ConfirmNumber', $instanceID)));
        $this->assertEquals(-1, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 0);
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->TriggerID, VM_UPDATE, [true]);
        $this->assertEquals(-1, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 0);
        TK_ResetStatus($instanceID);
        $this->assertEquals(0, GetValue(IPS_GetObjectIDByIdent('Status', $instanceID)));
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->TriggerID, VM_UPDATE, [true]);
        $this->assertEquals(count(VOIP_StubsGetConnections($this->VoIPID)), 1);
    }

    public function testCallSameNumber()
    {
        //Call same number multiple times
        $instanceID = $this->TelefonketteID;
        IPS_SetProperty($instanceID, 'PhoneNumbers', json_encode(
            [
                [
                    'PhoneNumber' => '111111'
                ],
                [
                    'PhoneNumber' => '111111'
                ],
                [
                    'PhoneNumber' => '111111'
                ]
            ]
        ));
        IPS_SetProperty($instanceID, 'MaxSyncCallCount', 3);
        IPS_ApplyChanges($instanceID);
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:01'));
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:02'));
        TK_UpdateCalls($instanceID);
        $this->assertEquals(3, count(VOIP_StubsGetConnections($this->VoIPID)));
    }

    public function testCallMultiTrigger()
    {
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        TK_setTime($instanceID, strtotime('September 1 2020 13:00:01'));
        TK_UpdateCalls($instanceID);
        $this->assertEquals(2, count(VOIP_StubsGetConnections($this->VoIPID)));
        IPS\InstanceManager::getInstanceInterface($instanceID)->MessageSink(0, $this->TriggerID, VM_UPDATE, [true]);
        $this->assertEquals(2, count(VOIP_StubsGetConnections($this->VoIPID)));
    }

    public function testCallForeigen()
    {
        //confirm and keep foreigen calls
        $instanceID = $this->TelefonketteID;
        TK_UpdateCalls($instanceID);
        $foreignCall = VOIP_Connect($this->VoIPID, '424242');
        $this->assertEquals(2, count(VOIP_StubsGetConnections($this->VoIPID)));
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