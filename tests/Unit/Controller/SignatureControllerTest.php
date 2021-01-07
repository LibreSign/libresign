<?php

namespace OCA\Signer\Tests\Unit\Controller;

use OCA\Signer\Controller\SignatureController;
use OCA\Signer\Service\SignatureService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class SignatureControllerTest extends TestCase
{
    public function testGenerate()
    {
        $userId = 'john';
        $request = $this->prophesize(IRequest::class);
        $service = $this->prophesize(SignatureService::class);

        $commonName = 'someCommonName';
        $hosts = 'someHosts';
        $country = 'someCountry';
        $organization = 'someOrganization';
        $organizationUnit = 'someOrganizationUnit';
        $path = '/path/to/somePath';
        $password = 'somePassword';

        $service->generate(
            $commonName,
            [$hosts],
            $country,
            $organization,
            $organizationUnit,
            $path,
            $password
        )
            ->shouldBeCalled()
            ->willReturn('/path/to/someSignature')
        ;

        $controller = new SignatureController(
                $request->reveal(),
                $service->reveal(),
                $userId
            );

        $result = $controller->generate(
            $commonName,
            $hosts,
            $country,
            $organization,
            $organizationUnit,
            $path,
            $password
        );

        static::assertSame(['signature' => '/path/to/someSignature'], $result->getData());
    }

    public function failParameterMissingProvider()
    {
        $commonName = 'someCommonName';
        $hosts = 'someHosts';
        $country = 'someCountry';
        $organization = 'someOrganization';
        $organizationUnit = 'someOrganizationUnit';
        $path = '/path/to/somePath';
        $password = 'somePassword';

        return [
            [null, $hosts, $country, $organization, $organizationUnit, $path, $password, 'commonName'],
            [$commonName, null, $country, $organization, $organizationUnit, $path, $password, 'hosts'],
            [$commonName, $hosts, null, $organization, $organizationUnit, $path, $password, 'country'],
            [$commonName, $hosts, $country, null, $organizationUnit, $path, $password, 'organization'],
            [$commonName, $hosts, $country, $organization, null, $path, $password, 'organizationUnit'],
            [$commonName, $hosts, $country, $organization, $organizationUnit, null, $password, 'path'],
            [$commonName, $hosts, $country, $organization, $organizationUnit, $path, null, 'password'],
        ];
    }

    /** @dataProvider failParameterMissingProvider */
    public function testGenerateFailParameterMissing(
        $commonName,
        $hosts,
        $country,
        $organization,
        $organizationUnit,
        $path,
        $password,
        $paramenterMissing
    ) {
        $userId = 'john';
        $request = $this->prophesize(IRequest::class);
        $service = $this->prophesize(SignatureService::class);

        $service->generate(\Prophecy\Argument::cetera())
            ->shouldNotBeCalled()
        ;

        $controller = new SignatureController(
            $request->reveal(),
            $service->reveal(),
            $userId
        );

        $result = $controller->generate(
            $commonName,
            $hosts,
            $country,
            $organization,
            $organizationUnit,
            $path,
            $password,
        );

        static::assertSame(['message' => "parameter '{$paramenterMissing}' is required!"], $result->getData());
        static::assertSame(400, $result->getStatus());
    }
}
