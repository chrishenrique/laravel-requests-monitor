<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

class HelperTest extends TestCase
{
    public function test_register_action_logs_a_manual_event(): void
    {
        registerAction('invoice.paid', null, ['invoice' => 10]);

        $record = $this->lastRecord();

        $this->assertSame('EVENT', $record->method);
        $this->assertSame('invoice.paid', $record->action_name);
        $this->assertSame('tests', $record->domain);
        $this->assertSame(['invoice' => 10], $record->content);
    }
}
