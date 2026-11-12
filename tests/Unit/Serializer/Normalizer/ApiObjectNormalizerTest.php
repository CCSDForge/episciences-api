<?php

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Serializer\Normalizer\ApiObjectNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiObjectNormalizerTest extends TestCase
{
    public function testGeneratedIriIsRemoved(): void
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/.well-known/genid/863f1592b3568272065e', '@type' => 'UserRoles', 'roleid' => 'member']);

        $this->assertSame(['@type' => 'UserRoles', 'roleid' => 'member'], new ApiObjectNormalizer($decorated)->normalize(new \stdClass(), 'jsonld'));
    }

    public function testRealIriIsKept(): void
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/users/1', '@type' => 'User']);

        $this->assertSame(['@id' => '/api/users/1', '@type' => 'User'], new ApiObjectNormalizer($decorated)->normalize(new \stdClass(), 'jsonld'));
    }
}
