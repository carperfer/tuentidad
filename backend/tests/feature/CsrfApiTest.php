<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class CsrfApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testReturnsHeaderNameAndToken(): void
    {
        $result = $this->get('api/csrf');

        $result->assertOK();
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('X-CSRF-TOKEN', $json['header']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $json['token']);
    }
}
