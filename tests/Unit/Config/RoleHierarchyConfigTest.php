<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Yaml\Yaml;

/**
 * The role hierarchy (config/packages/security.yaml) follows the one of Episciences (Episciences_Acl), with the
 * role ids of USER_ROLES: a token holds "ROLE_" + the upper case role id of its journal.
 */
class RoleHierarchyConfigTest extends TestCase
{
    /** Role ids of USER_ROLES (Episciences_Acl) */
    private const array ROLE_IDS = [
        'epiadmin', 'chief_editor', 'administrator', 'secretary', 'editor', 'guest_editor', 'reviewer', 'copyeditor',
        'webmaster', 'author', 'member', 'editorial_board', 'technical_board', 'scientific_advisory_board',
        'advisory_board', 'managing_editor', 'handling_editor', 'former_member',
    ];

    private RoleHierarchy $hierarchy;

    protected function setUp(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/security.yaml');
        $map = array_map(
            static fn(mixed $roles): array => (array)($roles ?? []),
            $config['security']['role_hierarchy']
        );

        $this->hierarchy = new RoleHierarchy($map);
    }

    /**
     * @return list<string>
     */
    private function reachable(string $roleId): array
    {
        return $this->hierarchy->getReachableRoleNames(['ROLE_' . strtoupper($roleId)]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleIdProvider(): array
    {
        return array_combine(self::ROLE_IDS, array_map(static fn(string $id): array => [$id], self::ROLE_IDS));
    }

    /**
     * Every role of a journal is a connected member (ROLE_MEMBER, ROLE_USER): none is missing from the hierarchy.
     */
    #[DataProvider('roleIdProvider')]
    public function testEveryRoleOfAJournalIsAMember(string $roleId): void
    {
        $this->assertContains('ROLE_MEMBER', $this->reachable($roleId));
        $this->assertContains('ROLE_USER', $this->reachable($roleId));
    }

    /**
     * epiadmin > chief editor > administrator > secretary > editor > guest editor > reviewer > member
     */
    public function testEditorialChainOfEpisciences(): void
    {
        $chain = ['epiadmin', 'chief_editor', 'administrator', 'secretary', 'editor', 'guest_editor', 'reviewer', 'member'];

        foreach ($chain as $index => $roleId) {
            foreach (array_slice($chain, $index) as $inherited) {
                $this->assertContains('ROLE_' . strtoupper($inherited), $this->reachable($roleId), "$roleId > $inherited");
            }
        }
    }

    /**
     * As in Episciences, the copy editors and the webmasters are apart: a secretary is neither of them.
     */
    public function testCopyEditorAndWebmasterAreApart(): void
    {
        foreach (['epiadmin', 'secretary', 'editor'] as $roleId) {
            $this->assertNotContains('ROLE_COPYEDITOR', $this->reachable($roleId), $roleId);
            $this->assertNotContains('ROLE_WEBMASTER', $this->reachable($roleId), $roleId);
        }

        $this->assertSame(['ROLE_COPYEDITOR', 'ROLE_MEMBER', 'ROLE_USER', 'ROLE_GUEST'], $this->reachable('copyeditor'));
    }
}
