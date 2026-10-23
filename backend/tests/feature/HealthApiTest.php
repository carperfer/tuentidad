<?php

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class HealthApiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthRespondsWithJsonStatus(): void
    {
        $result = $this->get('api/health');

        $this->assertContains($result->response()->getStatusCode(), [200, 503]);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('database', $json);
    }

    public function testUnknownRouteReturns404(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('api/no-existe');
    }
}
