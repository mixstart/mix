<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FetchTest extends TestCase
{

    public function testValue(): void
    {
        $_this = $this;
        $func  = function () use ($_this) {
            $conn   = conn();
            $result = $conn->table('users')->value('text');
            $_this->assertContains($result, 'test1');
        };
        run($func);
    }

}
