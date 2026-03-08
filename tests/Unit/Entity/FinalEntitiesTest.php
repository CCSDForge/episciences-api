<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LegacyNews;
use App\Entity\PaperSettings;
use App\Entity\PaperStat;
use App\Entity\RefreshToken;
use App\Entity\Reminders;
use App\Entity\UserInvitationAnswer;
use App\Entity\UserInvitationAnswerDetail;
use App\Entity\VolumePaperPosition;
use App\Entity\WebsiteSettings;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for remaining simple data-holder entities.
 */
final class FinalEntitiesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // Reminders
    // ══════════════════════════════════════════════════════════════════════════

    public function testRemindersDefaultRvidIsNull(): void
    {
        $r = new Reminders();
        $this->assertNull($r->getRvid());
    }

    public function testRemindersDefaultTypeIsNull(): void
    {
        $r = new Reminders();
        $this->assertNull($r->getType());
    }

    public function testRemindersDefaultDelayIsNull(): void
    {
        $r = new Reminders();
        $this->assertNull($r->getDelay());
    }

    public function testRemindersDefaultRecipientIsReviewer(): void
    {
        $r = new Reminders();
        $this->assertSame('reviewer', $r->getRecipient());
    }

    public function testRemindersDefaultRepetitionIsNull(): void
    {
        $r = new Reminders();
        $this->assertNull($r->getRepetition());
    }

    public function testRemindersSetRvidReturnsSelf(): void
    {
        $r = new Reminders();
        $result = $r->setRvid(3);
        $this->assertSame($r, $result);
    }

    public function testRemindersGetRvidReturnsSetValue(): void
    {
        $r = new Reminders();
        $r->setRvid(5);
        $this->assertSame(5, $r->getRvid());
    }

    public function testRemindersSetRvidWithNullResetsToNull(): void
    {
        $r = new Reminders();
        $r->setRvid(3);
        $r->setRvid(null);
        $this->assertNull($r->getRvid());
    }

    public function testRemindersSetTypeReturnsSelf(): void
    {
        $r = new Reminders();
        $result = $r->setType(true);
        $this->assertSame($r, $result);
    }

    public function testRemindersGetTypeReturnsSetValue(): void
    {
        $r = new Reminders();
        $r->setType(false);
        $this->assertFalse($r->getType());
    }

    public function testRemindersSetDelayReturnsSelf(): void
    {
        $r = new Reminders();
        $result = $r->setDelay(7);
        $this->assertSame($r, $result);
    }

    public function testRemindersGetDelayReturnsSetValue(): void
    {
        $r = new Reminders();
        $r->setDelay(14);
        $this->assertSame(14, $r->getDelay());
    }

    public function testRemindersSetRecipientReturnsSelf(): void
    {
        $r = new Reminders();
        $result = $r->setRecipient('author');
        $this->assertSame($r, $result);
    }

    public function testRemindersGetRecipientReturnsSetValue(): void
    {
        $r = new Reminders();
        $r->setRecipient('editor');
        $this->assertSame('editor', $r->getRecipient());
    }

    public function testRemindersSetRepetitionReturnsSelf(): void
    {
        $r = new Reminders();
        $result = $r->setRepetition('weekly');
        $this->assertSame($r, $result);
    }

    public function testRemindersGetRepetitionReturnsSetValue(): void
    {
        $r = new Reminders();
        $r->setRepetition('monthly');
        $this->assertSame('monthly', $r->getRepetition());
    }

    public function testRemindersSetRepetitionWithNullResetsToNull(): void
    {
        $r = new Reminders();
        $r->setRepetition('daily');
        $r->setRepetition(null);
        $this->assertNull($r->getRepetition());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LegacyNews
    // ══════════════════════════════════════════════════════════════════════════

    public function testLegacyNewsConstructorSetsDatePost(): void
    {
        $before = new DateTime();
        $ln = new LegacyNews();
        $after = new DateTime();

        $this->assertNotNull($ln->getDatePost());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $ln->getDatePost()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $ln->getDatePost()->getTimestamp());
    }

    public function testLegacyNewsSetRvidReturnsSelf(): void
    {
        $ln = new LegacyNews();
        $result = $ln->setRvid(1);
        $this->assertSame($ln, $result);
    }

    public function testLegacyNewsGetRvidReturnsSetValue(): void
    {
        $ln = new LegacyNews();
        $ln->setRvid(10);
        $this->assertSame(10, $ln->getRvid());
    }

    public function testLegacyNewsSetUidReturnsSelf(): void
    {
        $ln = new LegacyNews();
        $result = $ln->setUid(42);
        $this->assertSame($ln, $result);
    }

    public function testLegacyNewsGetUidReturnsSetValue(): void
    {
        $ln = new LegacyNews();
        $ln->setUid(99);
        $this->assertSame(99, $ln->getUid());
    }

    public function testLegacyNewsSetLinkReturnsSelf(): void
    {
        $ln = new LegacyNews();
        $result = $ln->setLink('https://example.org');
        $this->assertSame($ln, $result);
    }

    public function testLegacyNewsGetLinkReturnsSetValue(): void
    {
        $ln = new LegacyNews();
        $ln->setLink('https://episciences.org/news/1');
        $this->assertSame('https://episciences.org/news/1', $ln->getLink());
    }

    public function testLegacyNewsSetOnlineReturnsSelf(): void
    {
        $ln = new LegacyNews();
        $result = $ln->setOnline(true);
        $this->assertSame($ln, $result);
    }

    public function testLegacyNewsGetOnlineReturnsTrueWhenSet(): void
    {
        $ln = new LegacyNews();
        $ln->setOnline(true);
        $this->assertTrue($ln->getOnline());
    }

    public function testLegacyNewsGetOnlineReturnsFalseWhenSet(): void
    {
        $ln = new LegacyNews();
        $ln->setOnline(false);
        $this->assertFalse($ln->getOnline());
    }

    public function testLegacyNewsSetDatePostReturnsSelf(): void
    {
        $ln = new LegacyNews();
        $dt = new DateTime('2024-01-01');
        $result = $ln->setDatePost($dt);
        $this->assertSame($ln, $result);
    }

    public function testLegacyNewsGetDatePostReturnsSetValue(): void
    {
        $ln = new LegacyNews();
        $dt = new DateTime('2023-06-15');
        $ln->setDatePost($dt);
        $this->assertSame($dt, $ln->getDatePost());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperSettings
    // ══════════════════════════════════════════════════════════════════════════

    public function testPaperSettingsDefaultValueIsNull(): void
    {
        $ps = new PaperSettings();
        $this->assertNull($ps->getValue());
    }

    public function testPaperSettingsSetDocidReturnsSelf(): void
    {
        $ps = new PaperSettings();
        $result = $ps->setDocid(100);
        $this->assertSame($ps, $result);
    }

    public function testPaperSettingsGetDocidReturnsSetValue(): void
    {
        $ps = new PaperSettings();
        $ps->setDocid(200);
        $this->assertSame(200, $ps->getDocid());
    }

    public function testPaperSettingsSetSettingReturnsSelf(): void
    {
        $ps = new PaperSettings();
        $result = $ps->setSetting('allowReview');
        $this->assertSame($ps, $result);
    }

    public function testPaperSettingsGetSettingReturnsSetValue(): void
    {
        $ps = new PaperSettings();
        $ps->setSetting('displayCover');
        $this->assertSame('displayCover', $ps->getSetting());
    }

    public function testPaperSettingsSetValueReturnsSelf(): void
    {
        $ps = new PaperSettings();
        $result = $ps->setValue('1');
        $this->assertSame($ps, $result);
    }

    public function testPaperSettingsGetValueReturnsSetValue(): void
    {
        $ps = new PaperSettings();
        $ps->setValue('yes');
        $this->assertSame('yes', $ps->getValue());
    }

    public function testPaperSettingsSetValueWithNullResetsToNull(): void
    {
        $ps = new PaperSettings();
        $ps->setValue('old');
        $ps->setValue(null);
        $this->assertNull($ps->getValue());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperStat
    // ══════════════════════════════════════════════════════════════════════════

    public function testPaperStatDefaultAgentIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getAgent());
    }

    public function testPaperStatDefaultDomainIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getDomain());
    }

    public function testPaperStatDefaultContinentIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getContinent());
    }

    public function testPaperStatDefaultCountryIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getCountry());
    }

    public function testPaperStatDefaultCityIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getCity());
    }

    public function testPaperStatDefaultLatIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getLat());
    }

    public function testPaperStatDefaultLonIsNull(): void
    {
        $ps = new PaperStat();
        $this->assertNull($ps->getLon());
    }

    public function testPaperStatSetRobotReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setRobot(true);
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetRobotReturnsTrue(): void
    {
        $ps = new PaperStat();
        $ps->setRobot(true);
        $this->assertTrue($ps->getRobot());
    }

    public function testPaperStatGetRobotReturnsFalse(): void
    {
        $ps = new PaperStat();
        $ps->setRobot(false);
        $this->assertFalse($ps->getRobot());
    }

    public function testPaperStatSetAgentReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setAgent('curl/7.0');
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetAgentReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $ps->getAgent());
    }

    public function testPaperStatSetDomainReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setDomain('example.org');
        $this->assertSame($ps, $result);
    }

    public function testPaperStatSetContinentReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setContinent('EU');
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetContinentReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setContinent('AS');
        $this->assertSame('AS', $ps->getContinent());
    }

    public function testPaperStatSetCountryReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setCountry('FR');
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetCountryReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setCountry('DE');
        $this->assertSame('DE', $ps->getCountry());
    }

    public function testPaperStatSetCityReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setCity('Paris');
        $this->assertSame($ps, $result);
    }

    public function testPaperStatSetLatReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setLat(48.8566);
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetLatReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setLat(51.5074);
        $this->assertSame(51.5074, $ps->getLat());
    }

    public function testPaperStatSetLonReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setLon(2.3522);
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetLonReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setLon(-0.1278);
        $this->assertSame(-0.1278, $ps->getLon());
    }

    public function testPaperStatSetCounterReturnsSelf(): void
    {
        $ps = new PaperStat();
        $result = $ps->setCounter(5);
        $this->assertSame($ps, $result);
    }

    public function testPaperStatGetCounterReturnsSetValue(): void
    {
        $ps = new PaperStat();
        $ps->setCounter(100);
        $this->assertSame(100, $ps->getCounter());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VolumePaperPosition
    // ══════════════════════════════════════════════════════════════════════════

    public function testVolumePaperPositionSetVidReturnsSelf(): void
    {
        $vpp = new VolumePaperPosition();
        $result = $vpp->setVid(10);
        $this->assertSame($vpp, $result);
    }

    public function testVolumePaperPositionGetVidReturnsSetValue(): void
    {
        $vpp = new VolumePaperPosition();
        $vpp->setVid(42);
        $this->assertSame(42, $vpp->getVid());
    }

    public function testVolumePaperPositionSetPaperidReturnsSelf(): void
    {
        $vpp = new VolumePaperPosition();
        $result = $vpp->setPaperid(100);
        $this->assertSame($vpp, $result);
    }

    public function testVolumePaperPositionGetPaperidReturnsSetValue(): void
    {
        $vpp = new VolumePaperPosition();
        $vpp->setPaperid(999);
        $this->assertSame(999, $vpp->getPaperid());
    }

    public function testVolumePaperPositionSetPositionReturnsSelf(): void
    {
        $vpp = new VolumePaperPosition();
        $result = $vpp->setPosition(3);
        $this->assertSame($vpp, $result);
    }

    public function testVolumePaperPositionGetPositionReturnsSetValue(): void
    {
        $vpp = new VolumePaperPosition();
        $vpp->setPosition(7);
        $this->assertSame(7, $vpp->getPosition());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UserInvitationAnswer
    // ══════════════════════════════════════════════════════════════════════════

    public function testUserInvitationAnswerSetAnswerReturnsSelf(): void
    {
        $ia = new UserInvitationAnswer();
        $result = $ia->setAnswer('accepted');
        $this->assertSame($ia, $result);
    }

    public function testUserInvitationAnswerGetAnswerReturnsSetValue(): void
    {
        $ia = new UserInvitationAnswer();
        $ia->setAnswer('declined');
        $this->assertSame('declined', $ia->getAnswer());
    }

    public function testUserInvitationAnswerSetAnswerDateReturnsSelf(): void
    {
        $ia = new UserInvitationAnswer();
        $dt = new DateTime('2024-05-01');
        $result = $ia->setAnswerDate($dt);
        $this->assertSame($ia, $result);
    }

    public function testUserInvitationAnswerGetAnswerDateReturnsSetValue(): void
    {
        $ia = new UserInvitationAnswer();
        $dt = new DateTime('2023-12-01');
        $ia->setAnswerDate($dt);
        $this->assertSame($dt, $ia->getAnswerDate());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UserInvitationAnswerDetail
    // ══════════════════════════════════════════════════════════════════════════

    public function testUserInvitationAnswerDetailDefaultValueIsNull(): void
    {
        $iad = new UserInvitationAnswerDetail();
        $this->assertNull($iad->getValue());
    }

    public function testUserInvitationAnswerDetailSetValueReturnsSelf(): void
    {
        $iad = new UserInvitationAnswerDetail();
        $result = $iad->setValue('some-value');
        $this->assertSame($iad, $result);
    }

    public function testUserInvitationAnswerDetailGetValueReturnsSetValue(): void
    {
        $iad = new UserInvitationAnswerDetail();
        $iad->setValue('detail-value');
        $this->assertSame('detail-value', $iad->getValue());
    }

    public function testUserInvitationAnswerDetailSetValueWithNullResetsToNull(): void
    {
        $iad = new UserInvitationAnswerDetail();
        $iad->setValue('old');
        $iad->setValue(null);
        $this->assertNull($iad->getValue());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WebsiteSettings
    // ══════════════════════════════════════════════════════════════════════════

    public function testWebsiteSettingsSetValueReturnsSelf(): void
    {
        $ws = new WebsiteSettings();
        $result = $ws->setValue('red');
        $this->assertSame($ws, $result);
    }

    public function testWebsiteSettingsGetValueReturnsSetValue(): void
    {
        $ws = new WebsiteSettings();
        $ws->setValue('blue');
        $this->assertSame('blue', $ws->getValue());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RefreshToken
    // ══════════════════════════════════════════════════════════════════════════

    public function testRefreshTokenDefaultRvIdIsNull(): void
    {
        $rt = new RefreshToken();
        $this->assertNull($rt->getRvId());
    }

    public function testRefreshTokenDefaultDateIsNull(): void
    {
        $rt = new RefreshToken();
        $this->assertNull($rt->getDate());
    }

    public function testRefreshTokenSetRvIdReturnsSelf(): void
    {
        $rt = new RefreshToken();
        $result = $rt->setRvId(5);
        $this->assertSame($rt, $result);
    }

    public function testRefreshTokenGetRvIdReturnsSetValue(): void
    {
        $rt = new RefreshToken();
        $rt->setRvId(10);
        $this->assertSame(10, $rt->getRvId());
    }

    public function testRefreshTokenSetRvIdWithNullResetsToNull(): void
    {
        $rt = new RefreshToken();
        $rt->setRvId(3);
        $rt->setRvId(null);
        $this->assertNull($rt->getRvId());
    }

    public function testRefreshTokenSetDateSetsCurrentDatetime(): void
    {
        $before = new DateTime();
        $rt = new RefreshToken();
        $rt->setDate();
        $after = new DateTime();

        $this->assertNotNull($rt->getDate());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $rt->getDate()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $rt->getDate()->getTimestamp());
    }

    public function testRefreshTokenSetDateReturnsSelf(): void
    {
        $rt = new RefreshToken();
        $result = $rt->setDate();
        $this->assertSame($rt, $result);
    }
}
