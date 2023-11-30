<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class TelefonketteValidationTest extends TestCaseSymconValidation
{
    public function testValidateTelefonkette(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidatePhoneChainModule(): void
    {
        $this->validateModule(__DIR__ . '/../PhoneChain');
    }
}