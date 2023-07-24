<?php

declare(strict_types=1);

include_once __DIR__ . '/timeTrait.php';
class Telefonkette extends IPSModule
{
    use TestTime;

    const VOIP_EVENT = 21000;
    const WAITING = 0;
    const CONFIRMED = -1;
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Attributes
        $this->RegisterAttributeString('ActiveCalls', '[]');
        $this->RegisterAttributeInteger('ListPosition', '0');

        //Properties
        $this->RegisterPropertyInteger('Trigger', 0);
        $this->RegisterPropertyInteger('VoIP', 0);
        $this->RegisterPropertyInteger('TTS', 0);
        $this->RegisterPropertyBoolean('OutputOption', false);
        $this->RegisterPropertyString('StaticOutput', '');
        $this->RegisterPropertyInteger('VariableOutput', 0);
        $this->RegisterPropertyString('PhoneNumbers', '[]');
        $this->RegisterPropertyInteger('MaxSyncCallCount', 2);
        $this->RegisterPropertyInteger('CallDuration', 15);
        $this->RegisterPropertyString('ConfirmKey', '1');
        $this->RegisterPropertyBoolean('ResetStatus', false);
        $this->RegisterPropertyInteger('ResetInterval', 10);

        //Profiles
        if (!IPS_VariableProfileExists('TK.Status')) {
            IPS_CreateVariableProfile('TK.Status', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('TK.Status', -1, $this->Translate('Confirmed'), '', -1);
            IPS_SetVariableProfileAssociation('TK.Status', 0, $this->Translate('Ready'), '', -1);
            IPS_SetVariableProfileAssociation('TK.Status', 1, $this->Translate('List Position %d'), '', -1);
        }

        //Variables
        $this->RegisterVariableString('ConfirmNumber', $this->Translate('Call Confirmed by'), '', 0);
        $this->RegisterVariableInteger('Status', $this->Translate('Status'), 'TK.Status', 1);

        //Timer
        $this->RegisterTimer('UpdateCall', 0, 'TK_UpdateCalls($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ResetStatus', 0, 'TK_ResetStatus($_IPS[\'TARGET\']);');

        //Scripts
        $this->RegisterScript('ResetStatus', $this->Translate('Reset Status'), "<?php\nTK_ResetStatus($this->InstanceID);", 2);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetBuffer('ActiveCalls', '[]');
        $this->SetBuffer('ListPosition', '0');

        $this->setErrorState();
        if ($this->GetStatus() != 102) {
            return;
        }
        //Unregister all previous registered messages
        foreach ($this->GetMessageList() as $objectID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($objectID, $message);
            }
        }
        $this->RegisterMessage($this->ReadPropertyInteger('Trigger'), VM_UPDATE);
        $this->RegisterMessage($this->ReadPropertyInteger('VoIP'), self::VOIP_EVENT);

        //Disable automatic reset timer
        if (!$this->ReadPropertyBoolean('ResetStatus')) {
            $this->SetTimerInterval('ResetStatus', 0);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data)
    {
        switch ($MessageID) {
            case VM_UPDATE:
                if ($Data[0] && ($this->GetStatus() == 102) && ($this->GetValue('Status') == self::WAITING)) {
                    $this->SetTimerInterval('UpdateCall', 1000);
                    $this->UpdateCalls();
                }
                break;

            case self::VOIP_EVENT:
                //Only handle messages for our active calls
                if (!array_key_exists($Data[0], json_decode($this->GetBuffer('ActiveCalls'), true))) {
                    return;
                }
                $this->SendDebug('VoIP', $this->Translate('A DTMF signal was received'), 0);
                switch ($Data[1]) {
                    case 'DTMF':
                        $this->SendDebug('VoIP', $this->Translate('A DTMF signal was received'), 0);
                        switch ($Data[2]) {
                            case $this->ReadPropertyString('ConfirmKey'):
                                $this->SetValue('ConfirmNumber', VoIP_GetConnection($this->ReadPropertyInteger('VoIP'), $Data[0])['Number']);
                                $this->SetValue('Status', self::CONFIRMED);
                                VoIP_Disconnect($this->ReadPropertyInteger('VoIP'), $Data[0]);
                                //If confirmed end all remaining calls
                                $activeCalls = json_decode($this->GetBuffer('ActiveCalls'), true);
                                $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
                                foreach ($activeCalls as $activeCallID => $activeCallTime) {
                                    VoIP_Disconnect($this->ReadPropertyInteger('VoIP'), $activeCallID);
                                }
                                $this->reset();
                                //Start auto reset timer if enabled
                                if ($this->ReadPropertyBoolean('ResetStatus')) {
                                    $this->SetTimerInterval('ResetStatus', $this->ReadPropertyInteger('ResetInterval') * 1000 * 60);
                                }
                                break;

                            default:
                                $this->SendDebug('Telefonkette', $this->Translate('Unprocessed DTMF symbol:') . ' ' . $Data[2], 0);
                                break;
                            }
                            break;

                    default:
                        $this->SendDebug('Telefonkette', $this->Translate('Unprocessed VoIP event:') . ' ' . $Data[1], 0);
                        break;
                }
                break;

            default:
                break;
            }
    }

    public function UISetVisible(bool $value)
    {
        $this->UpdateFormField('StaticOutput', 'visible', !$value);
        $this->UpdateFormField('VariableOutput', 'visible', $value);
    }

    public function UpdateCalls()
    {
        if ($this->GetStatus() != 102) {
            return;
        }
        //Check if remaining calls exceed the time limit
        $activeCalls = json_decode($this->GetBuffer('ActiveCalls'), true);
        $voipID = $this->ReadPropertyInteger('VoIP');
        foreach ($activeCalls as $activeCallID => $activeCallTime) {
            $call = VoIP_GetConnection($voipID, $activeCallID);
            $this->SendDebug('Telefonkette', sprintf($this->Translate('Time: %s | Call Time: %s'), date('H:i:s d.m.Y', $this->GetTime()), date('H:i:s d.m.Y', $activeCallTime)), 0);
            //If the call is answered don't end it
            if ($call['Connected']) {
                $this->playTTS($voipID, $call);
                $this->SendDebug($call['Number'], 'Connected', 0);
                continue;
            }

            //End calls which exceed the time limit
            if (($this->getTime() - $activeCallTime) > $this->ReadPropertyInteger('CallDuration')) {
                VoIP_Disconnect($voipID, $activeCallID);
                unset($activeCalls[$activeCallID]);
                $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
            }
        }

        //If maxSyncCalls not reached and not at the end of the number list
        $phoneNumbers = json_decode($this->ReadPropertyString('PhoneNumbers'), true);
        $listPosition = json_decode($this->GetBuffer('ListPosition'));
        if ((count($activeCalls) < $this->ReadPropertyInteger('MaxSyncCallCount')) && ($listPosition < count($phoneNumbers))) {
            $call = VoIP_Connect($voipID, $phoneNumbers[$listPosition]['PhoneNumber']);
            $this->SetValue('Status', $listPosition + 1);
            $this->SendDebug('New Call', json_encode($call), 0);
            $this->SetBuffer('ListPosition', json_encode($listPosition + 1));
            $activeCalls[$call] = $this->getTime();
            $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
        }
        //Cancel if no one was reached
        elseif ((count($activeCalls) == 0) && ($listPosition == count($phoneNumbers))) {
            $this->SetValue('ConfirmNumber', $this->Translate('No one was reached'));
            $this->SetValue('Status', self::WAITING);
            $this->reset();
            $this->SendDebug('Telefonkette', $this->Translate('No one was reached'), 0);
        }
        $this->SetBuffer('ActiveCalls', json_encode($activeCalls));
    }

    public function ResetStatus()
    {
        $this->SetValue('Status', self::WAITING);
        $this->SetTimerInterval('ResetStatus', 0);
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $form['elements'][9]['visible'] = $this->ReadPropertyBoolean('ResetStatus');
        $form['elements'][3]['items'][1]['visible'] = !$this->ReadPropertyBoolean('OutputOption');
        $form['elements'][3]['items'][2]['visible'] = $this->ReadPropertyBoolean('OutputOption');
        return json_encode($form);
    }

    public function ToggleInterval(bool $visible)
    {
        $this->UpdateFormField('ResetInterval', 'visible', $visible);
    }

    private function playTTS($voipID, $voipConnection)
    {
        $ttsID = $this->ReadPropertyInteger('TTS');
        if(!IPS_InstanceExists($ttsID)){
            return;
        }

        switch ($this->ReadPropertyString('OutputOption')) {
            case 'StaticOutput':
                $output = $this->ReadPropertyString('StaticOutput');
                if($output !== ""){
                    $file = TTSAWSPOLLY_GenerateFile($ttsID, $output);
                }
                break;
            case 'VariableOutput':
                $outputID = $this->ReadPropertyInteger('VariableOutput');
                if(IPS_VariableExists($outputID)){
                    $file = TTSAWSPOLLY_GenerateFile($ttsID, GetValue($outputID));
                }
                break;
            default:
                $this->SendDebug('ERROR', 'OutputOption is not supported');
                return;
        }

        if(isset($file)){
            VOIP_PlayWave($voipID, $voipConnection['ID'], $file);
        }
    }

    private function setErrorState()
    {
        $getInstanceStatus = function ()
        {
            $trigger = $this->ReadPropertyInteger('Trigger');
            $voIP = $this->ReadPropertyInteger('VoIP');
            if ($trigger == 0) {
                return 104;
            }
            if (!IPS_VariableExists($trigger)) {
                return 200;
            }
            if (IPS_GetVariable($trigger)['VariableType'] != VARIABLETYPE_BOOLEAN) {
                return 201;
            }
            if ($voIP == 0) {
                return 104;
            }
            if (!IPS_InstanceExists($voIP)) {
                return 202;
            }
            if (IPS_GetInstance($voIP)['ModuleInfo']['ModuleID'] != '{A4224A63-49EA-445F-8422-22EF99D8F624}') {
                return 203;
            }
            if (count(json_decode($this->ReadPropertyString('PhoneNumbers'), true)) == 0) {
                return 104;
            }

            //Everything ok
            return 102;
        };

        $this->SetStatus($getInstanceStatus());
    }

    private function reset()
    {
        $this->SetTimerInterval('UpdateCall', 0);
        $this->SetBuffer('ListPosition', '0');
        $this->SetBuffer('ActiveCalls', '[]');
    }
}
