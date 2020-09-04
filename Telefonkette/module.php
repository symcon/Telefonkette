<?php

declare(strict_types=1);

include_once __DIR__ . '/timeTrait.php';
class Telefonkette extends IPSModule
{
    use TestTime;
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Attribures
        $this->RegisterAttributeString('ActiveCalls', '[]');
        $this->RegisterAttributeInteger('ListPosition', 0);

        //Properties
        $this->RegisterPropertyInteger('Trigger', 0);
        $this->RegisterPropertyInteger('VoIP', 0);
        $this->RegisterPropertyString('PhoneNumbers', '[]');
        $this->RegisterPropertyInteger('MaxSyncCallCount', 0);
        $this->RegisterPropertyInteger('CallDuration', 15);
        $this->RegisterPropertyString('ConfirmKey', '1');

        //Variables
        $this->RegisterVariableBoolean('CallConfirmed', $this->Translate('Call Confirmed'), '', 0);

        //Timer
        $this->RegisterTimer('UpdateCall', 0, 'TK_UpdateCalls($_IPS[\'TARGET\']);');
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

        //TODO: Check for valid VoIP instance
        //TODO: Check for valid Trigger instance

        $this->SetBuffer('ActiveCalls', '[]');
        $this->SetBuffer('ListPosition', 0);

        $this->RegisterMessage($this->ReadPropertyInteger('Trigger'), VM_UPDATE);
        $this->RegisterMessage($this->ReadPropertyInteger('VoIP'), 21000 /*VOIP_EVENT*/);
    }

    public function MessageSink($TimeStamp, $SenderID, $MessageID, $Data)
    {
        switch ($MessageID) {
            case VM_UPDATE:
                if ($this->GetStatus() == 102) {
                    $this->SetTimerInterval('UpdateCall', 1000);
                }
                break;
            case 21000 /*VOIP_EVENT*/:
                //Only handle messages for our active calls
                if (!array_key_exists($Data[0], json_decode($this->GetBuffer('ActiveCalls'), true))) {
                    return;
                }
                switch ($Data[1]) {
                    case 'DTMF':
                        IPS_LogMessage('VoIP', 'Es wurde ein DTMF Signal empfangen');
                        switch ($Data[2]) {
                            case '1':
                                $this->SetTimerInterval('UpdateCall', 0);
                                $this->SetBuffer('ListPosition', 0);

                                //Wenn Abbruch dann alle aktiven Anrufe beenden
                                $activeCalls = json_decode($this->GetBuffer('ActiveCalls'), true);
                                $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
                                foreach ($activeCalls as $activeCallID => $activeCallTime) {
                                    VoIP_Disconnect($this->ReadPropertyInteger('VoIP'), $activeCallID);
                                }
                                $this->SetBuffer('ActiveCalls', '[]');
                            break;

                            default:
                                //Default DTMF
                        break;
                        }
                        // FIXME: No break. Please add proper comment if intentional
                    default:
                        //Throw error
                        break;
                }
            break;

            default:
                //TODO: throw error
            }
    }

    public function UpdateCalls()
    {
        //Liste der aktiven Anrufe durchgehen ob Zeit überschritten
        $activeCalls = json_decode($this->GetBuffer('ActiveCalls'), true);
        foreach ($activeCalls as $activeCallID => $activeCallTime) {
            IPS_LogMessage('Telefonkette', 'Zeit: ' . $this->getTime() . '  Anrufzeit:' . $activeCallTime);
            //Wenn der Anruf abgenommen wird, diesen nicht beenden
            $call = VoIP_GetConnection($this->ReadPropertyInteger('VoIP'), $activeCallID);
            $this->SendDebug('Call', json_encode($call), 0);
            if ($call['Connected']) {
                $activeCallTime = $this->getTime();
            }

            $difference = ($this->getTime() - $activeCallTime);
            if (($this->getTime() - $activeCallTime) > $this->ReadPropertyInteger('CallDuration')) {
                VoIP_Disconnect($this->ReadPropertyInteger('VoIP'), $activeCallID);
                unset($activeCalls[$activeCallID]);
                $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
            }
            $this->SetBuffer('ActiveCalls', json_encode($activeCalls));
        }

        //Wenn Platz und nicht am Ende der Telefonnummernliste -> dann weiterer Anruf
        $activeCalls = json_decode($this->GetBuffer('ActiveCalls'), true);
        $phoneNumbers = json_decode($this->ReadPropertyString('PhoneNumbers'), true);
        $listPosition = $this->GetBuffer('ListPosition');
        if ((count($activeCalls) < $this->ReadPropertyInteger('MaxSyncCallCount')) && ($listPosition < count($phoneNumbers))) {
            $call = VoIP_Connect($this->ReadPropertyInteger('VoIP'), $phoneNumbers[$listPosition]['PhoneNumber']);
            $this->SendDebug('Call', json_encode($call), 0);
            $this->SetBuffer('ListPosition', $listPosition + 1);
            $activeCalls[$call] = $this->getTime();
            $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
            $this->SetBuffer('ActiveCalls', json_encode($activeCalls));

        // Abbrechen wenn niemand erreicht wurde
        } elseif ((count($activeCalls) === 0) && ($listPosition === count($phoneNumbers))) {
            $this->SetTimerInterval('UpdateCall', 0);
            $this->SetBuffer('ListPosition', 0);
            $this->SendDebug('ActiveCalls', json_encode($activeCalls), 0);
            $this->SetBuffer('ActiveCalls', json_encode([]));
            IPS_LogMessage('Telefonkette', 'Es wurde niemand erreicht');
            return;
        }
    }
}
