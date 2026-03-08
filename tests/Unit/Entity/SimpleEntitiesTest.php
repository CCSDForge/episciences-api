<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Authors;
use App\Entity\PaperAuthors;
use App\Entity\VolumePaper;
use App\Entity\VolumeMetadata;
use App\Entity\VolumeProceeding;
use App\Entity\Volume;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for simple data-holder entities: Authors, PaperAuthors, VolumePaper.
 *
 * These entities have no business logic beyond getters/setters; the tests guard
 * against accidental signature regressions and verify default values.
 */
final class SimpleEntitiesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // Authors
    // ══════════════════════════════════════════════════════════════════════════

    public function testAuthorsSetFirstnameReturnsSelf(): void
    {
        $author = new Authors();
        $result = $author->setFirstname('John');
        $this->assertSame($author, $result);
    }

    public function testAuthorsGetFirstnameReturnsSetValue(): void
    {
        $author = new Authors();
        $author->setFirstname('Marie');
        $this->assertSame('Marie', $author->getFirstname());
    }

    public function testAuthorsSetLastnameReturnsSelf(): void
    {
        $author = new Authors();
        $result = $author->setLastname('Curie');
        $this->assertSame($author, $result);
    }

    public function testAuthorsGetLastnameReturnsSetValue(): void
    {
        $author = new Authors();
        $author->setLastname('Curie');
        $this->assertSame('Curie', $author->getLastname());
    }

    public function testAuthorsSetOrcidReturnsSelf(): void
    {
        $author = new Authors();
        $result = $author->setOrcid('0000-0002-1825-0097');
        $this->assertSame($author, $result);
    }

    public function testAuthorsGetOrcidReturnsSetValue(): void
    {
        $author = new Authors();
        $author->setOrcid('0000-0002-1825-0097');
        $this->assertSame('0000-0002-1825-0097', $author->getOrcid());
    }

    public function testAuthorsSetUidReturnsSelf(): void
    {
        $author = new Authors();
        $result = $author->setUid(42);
        $this->assertSame($author, $result);
    }

    public function testAuthorsGetUidReturnsSetValue(): void
    {
        $author = new Authors();
        $author->setUid(99);
        $this->assertSame(99, $author->getUid());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperAuthors
    // ══════════════════════════════════════════════════════════════════════════

    public function testPaperAuthorsDefaultUidIsZero(): void
    {
        $pa = new PaperAuthors();
        $this->assertSame(0, $pa->getUid());
    }

    public function testPaperAuthorsDefaultPositionIsNull(): void
    {
        $pa = new PaperAuthors();
        $this->assertNull($pa->getPosition());
    }

    public function testPaperAuthorsDefaultAutoridIsNull(): void
    {
        $pa = new PaperAuthors();
        $this->assertNull($pa->getAuthorid());
    }

    public function testPaperAuthorsSetDocidReturnsSelf(): void
    {
        $pa = new PaperAuthors();
        $result = $pa->setDocid(10);
        $this->assertSame($pa, $result);
    }

    public function testPaperAuthorsGetDocidReturnsSetValue(): void
    {
        $pa = new PaperAuthors();
        $pa->setDocid(55);
        $this->assertSame(55, $pa->getDocid());
    }

    public function testPaperAuthorsSetUidReturnsSelf(): void
    {
        $pa = new PaperAuthors();
        $result = $pa->setUid(7);
        $this->assertSame($pa, $result);
    }

    public function testPaperAuthorsGetUidReturnsSetValue(): void
    {
        $pa = new PaperAuthors();
        $pa->setUid(33);
        $this->assertSame(33, $pa->getUid());
    }

    public function testPaperAuthorsSetPositionReturnsSelf(): void
    {
        $pa = new PaperAuthors();
        $result = $pa->setPosition(2);
        $this->assertSame($pa, $result);
    }

    public function testPaperAuthorsGetPositionReturnsSetValue(): void
    {
        $pa = new PaperAuthors();
        $pa->setPosition(5);
        $this->assertSame(5, $pa->getPosition());
    }

    public function testPaperAuthorsSetPositionWithNullResetsToNull(): void
    {
        $pa = new PaperAuthors();
        $pa->setPosition(3);
        $pa->setPosition(null);
        $this->assertNull($pa->getPosition());
    }

    public function testPaperAuthorsSetAutoridReturnsSelf(): void
    {
        $pa = new PaperAuthors();
        $author = new Authors();
        $author->setFirstname('Test');
        $author->setLastname('Author');
        $author->setOrcid('');
        $author->setUid(1);
        $result = $pa->setAuthorid($author);
        $this->assertSame($pa, $result);
    }

    public function testPaperAuthorsGetAutoridReturnsSetValue(): void
    {
        $pa = new PaperAuthors();
        $author = $this->createStub(Authors::class);
        $pa->setAuthorid($author);
        $this->assertSame($author, $pa->getAuthorid());
    }

    public function testPaperAuthorsSetAutoridWithNullResetsToNull(): void
    {
        $pa = new PaperAuthors();
        $pa->setAuthorid($this->createStub(Authors::class));
        $pa->setAuthorid(null);
        $this->assertNull($pa->getAuthorid());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VolumePaper
    // ══════════════════════════════════════════════════════════════════════════

    public function testVolumePaperSetVidReturnsSelf(): void
    {
        $vp = new VolumePaper();
        $result = $vp->setVid(1);
        $this->assertSame($vp, $result);
    }

    public function testVolumePaperGetVidReturnsSetValue(): void
    {
        $vp = new VolumePaper();
        $vp->setVid(42);
        $this->assertSame(42, $vp->getVid());
    }

    public function testVolumePaperSetDocidReturnsSelf(): void
    {
        $vp = new VolumePaper();
        $result = $vp->setDocid(100);
        $this->assertSame($vp, $result);
    }

    public function testVolumePaperGetDocidReturnsSetValue(): void
    {
        $vp = new VolumePaper();
        $vp->setDocid(999);
        $this->assertSame(999, $vp->getDocid());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VolumeMetadata
    // ══════════════════════════════════════════════════════════════════════════

    public function testVolumeMetadataTableConstant(): void
    {
        $this->assertSame('VOLUME_METADATA', VolumeMetadata::TABLE);
    }

    public function testVolumeMetadataDefaultPositionIsNull(): void
    {
        $vm = new VolumeMetadata();
        $this->assertNull($vm->getPosition());
    }

    public function testVolumeMetadataDefaultContentIsEmptyArray(): void
    {
        $vm = new VolumeMetadata();
        $this->assertSame([], $vm->getContent());
    }

    public function testVolumeMetadataDefaultFileIsNull(): void
    {
        $vm = new VolumeMetadata();
        $this->assertNull($vm->getFile());
    }

    public function testVolumeMetadataDefaultDateCreationIsNull(): void
    {
        $vm = new VolumeMetadata();
        $this->assertNull($vm->getDateCreation());
    }

    public function testVolumeMetadataSetVidReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setVid(5);
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataGetVidReturnsSetValue(): void
    {
        $vm = new VolumeMetadata();
        $vm->setVid(10);
        $this->assertSame(10, $vm->getVid());
    }

    public function testVolumeMetadataSetPositionReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setPosition(3);
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataGetPositionReturnsSetValue(): void
    {
        $vm = new VolumeMetadata();
        $vm->setPosition(7);
        $this->assertSame(7, $vm->getPosition());
    }

    public function testVolumeMetadataSetTitlesReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setTitles(['en' => 'Intro']);
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataGetTitlesReturnsSetValue(): void
    {
        $vm = new VolumeMetadata();
        $vm->setTitles(['en' => 'About', 'fr' => 'À propos']);
        $this->assertSame(['en' => 'About', 'fr' => 'À propos'], $vm->getTitles());
    }

    public function testVolumeMetadataSetContentReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setContent(['text' => 'hello']);
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataGetContentReturnsSetValue(): void
    {
        $vm = new VolumeMetadata();
        $vm->setContent(['en' => 'Content here']);
        $this->assertSame(['en' => 'Content here'], $vm->getContent());
    }

    public function testVolumeMetadataSetFileReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setFile('doc.pdf');
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataSetFileWithNullResetsToNull(): void
    {
        $vm = new VolumeMetadata();
        $vm->setFile('file.pdf');
        $vm->setFile(null);
        $this->assertNull($vm->getFile());
    }

    public function testVolumeMetadataSetVolumeReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $vol = $this->createStub(Volume::class);
        $result = $vm->setVolume($vol);
        $this->assertSame($vm, $result);
    }

    public function testVolumeMetadataGetVolumeDefaultsToNull(): void
    {
        $vm = new VolumeMetadata();
        $this->assertNull($vm->getVolume());
    }

    public function testVolumeMetadataSetDateCreationReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setDateCreation(new DateTime('2023-01-01'));
        $this->assertInstanceOf(VolumeMetadata::class, $result);
    }

    public function testVolumeMetadataSetDateUpdatedReturnsSelf(): void
    {
        $vm = new VolumeMetadata();
        $result = $vm->setDateUpdated(new DateTime('2024-05-01'));
        $this->assertInstanceOf(VolumeMetadata::class, $result);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VolumeProceeding
    // ══════════════════════════════════════════════════════════════════════════

    public function testVolumeProceedingTableConstant(): void
    {
        $this->assertSame('volume_proceeding', VolumeProceeding::TABLE);
    }

    public function testVolumeProceedingDefaultVolumeIsNull(): void
    {
        $vp = new VolumeProceeding();
        $this->assertNull($vp->getVolume());
    }

    public function testVolumeProceedingSetSettingReturnsSelf(): void
    {
        $vp = new VolumeProceeding();
        $result = $vp->setSetting('allowSomething');
        $this->assertSame($vp, $result);
    }

    public function testVolumeProceedingGetSettingReturnsSetValue(): void
    {
        $vp = new VolumeProceeding();
        $vp->setSetting('displayCover');
        $this->assertSame('displayCover', $vp->getSetting());
    }

    public function testVolumeProceedingSetValueReturnsSelf(): void
    {
        $vp = new VolumeProceeding();
        $result = $vp->setValue('true');
        $this->assertSame($vp, $result);
    }

    public function testVolumeProceedingGetValueReturnsSetValue(): void
    {
        $vp = new VolumeProceeding();
        $vp->setValue('1');
        $this->assertSame('1', $vp->getValue());
    }

    public function testVolumeProceedingSetVolumeReturnsSelf(): void
    {
        $vp = new VolumeProceeding();
        $vol = $this->createStub(Volume::class);
        $result = $vp->setVolume($vol);
        $this->assertSame($vp, $result);
    }

    public function testVolumeProceedingGetVolumeReturnsSetValue(): void
    {
        $vp = new VolumeProceeding();
        $vol = $this->createStub(Volume::class);
        $vp->setVolume($vol);
        $this->assertSame($vol, $vp->getVolume());
    }

    public function testVolumeProceedingSetVolumeWithNullResetsToNull(): void
    {
        $vp = new VolumeProceeding();
        $vp->setVolume($this->createStub(Volume::class));
        $vp->setVolume(null);
        $this->assertNull($vp->getVolume());
    }
}
