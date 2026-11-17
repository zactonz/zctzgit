<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/autoload.php';

final class TestRun
{
    private int $passed = 0;
    private int $failed = 0;
    private string $group = '';

    public function group(string $name): void
    {
        $this->group = $name;
        echo PHP_EOL . $name . PHP_EOL;
    }

    public function assert(string $label, bool $ok): void
    {
        $ok ? $this->passed++ : $this->failed++;
        echo ($ok ? '  ok   ' : '  FAIL ') . $label . PHP_EOL;
    }

    public function same(string $label, mixed $expected, mixed $actual): void
    {
        $ok = $expected === $actual;
        $this->assert($label, $ok);
        if (!$ok) {
            echo '         expected: ' . var_export($expected, true) . PHP_EOL;
            echo '         actual:   ' . var_export($actual, true) . PHP_EOL;
        }
    }

    public function throws(string $label, callable $fn): void
    {
        try {
            $fn();
            $this->assert($label, false);
        } catch (\Throwable) {
            $this->assert($label, true);
        }
    }

    public function finish(): int
    {
        echo PHP_EOL . sprintf(
            '%d passed, %d failed (PHP %s)',
            $this->passed,
            $this->failed,
            PHP_VERSION,
        ) . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }
}

$t = new TestRun();

foreach (glob(__DIR__ . '/cases/*.php') ?: [] as $case) {
    (require $case)($t);
}

exit($t->finish());
