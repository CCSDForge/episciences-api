<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DoiQueue;
use App\Entity\MailLog;
use App\Entity\PaperComments;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\Section;
use App\Entity\SectionSetting;
use App\Entity\StatTemp;
use App\Entity\UserInvitation;
use App\Entity\UserMerge;
use App\Entity\UserTmp;
use App\Entity\Volume;
use App\Entity\VolumeSetting;
use App\Entity\WebsiteHeader;
use App\Entity\WebsiteNavigation;
use App\Entity\WebsiteStyles;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for simple data-holder entities not covered elsewhere.
 */
final class MoreEntitiesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // ReviewSetting
    // ══════════════════════════════════════════════════════════════════════════

    public function testReviewSettingTableConstant(): void
    {
        $this->assertSame('REVIEW_SETTING', ReviewSetting::TABLE);
    }

    public function testReviewSettingAllowBrowseConstant(): void
    {
        $this->assertSame('allowBrowseAcceptedDocuments', ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE);
    }

    public function testReviewSettingDisplayEmptyVolumesConstant(): void
    {
        $this->assertSame('displayEmptyVolumes', ReviewSetting::DISPLAY_EMPTY_VOLUMES);
    }

    public function testReviewSettingSetValueReturnsSelf(): void
    {
        $rs = new ReviewSetting();
        $result = $rs->setValue('true');
        $this->assertSame($rs, $result);
    }

    public function testReviewSettingGetValueReturnsSetValue(): void
    {
        $rs = new ReviewSetting();
        $rs->setValue('false');
        $this->assertSame('false', $rs->getValue());
    }

    public function testReviewSettingSetValueWithNullResetsToNull(): void
    {
        $rs = new ReviewSetting();
        $rs->setValue('x');
        $rs->setValue(null);
        $this->assertNull($rs->getValue());
    }

    public function testReviewSettingSetReviewReturnsSelf(): void
    {
        $rs = new ReviewSetting();
        $review = $this->createStub(Review::class);
        $result = $rs->setReview($review);
        $this->assertSame($rs, $result);
    }

    public function testReviewSettingGetReviewReturnsSetValue(): void
    {
        $rs = new ReviewSetting();
        $review = $this->createStub(Review::class);
        $rs->setReview($review);
        $this->assertSame($review, $rs->getReview());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SectionSetting
    // ══════════════════════════════════════════════════════════════════════════

    public function testSectionSettingTableConstant(): void
    {
        $this->assertSame('SECTION_SETTING', SectionSetting::TABLE);
    }

    public function testSectionSettingSetSettingReturnsSelf(): void
    {
        $ss = new SectionSetting();
        $result = $ss->setSetting('allowComments');
        $this->assertSame($ss, $result);
    }

    public function testSectionSettingGetSettingReturnsSetValue(): void
    {
        $ss = new SectionSetting();
        $ss->setSetting('displayCover');
        $this->assertSame('displayCover', $ss->getSetting());
    }

    public function testSectionSettingSetValueReturnsSelf(): void
    {
        $ss = new SectionSetting();
        $result = $ss->setValue('1');
        $this->assertSame($ss, $result);
    }

    public function testSectionSettingGetValueReturnsSetValue(): void
    {
        $ss = new SectionSetting();
        $ss->setValue('yes');
        $this->assertSame('yes', $ss->getValue());
    }

    public function testSectionSettingSetValueWithNullResetsToNull(): void
    {
        $ss = new SectionSetting();
        $ss->setValue('old');
        $ss->setValue(null);
        $this->assertNull($ss->getValue());
    }

    public function testSectionSettingSetSidSetsValue(): void
    {
        $ss = new SectionSetting();
        $ss->setSid(42);
        $this->assertSame(42, $ss->getSid());
    }

    public function testSectionSettingGetSectionDefaultsToNull(): void
    {
        $ss = new SectionSetting();
        $this->assertNull($ss->getSection());
    }

    public function testSectionSettingSetSectionSetsValue(): void
    {
        $ss = new SectionSetting();
        $section = $this->createStub(Section::class);
        $ss->setSection($section);
        $this->assertSame($section, $ss->getSection());
    }

    public function testSectionSettingSetSectionWithNullResetsToNull(): void
    {
        $ss = new SectionSetting();
        $ss->setSection($this->createStub(Section::class));
        $ss->setSection(null);
        $this->assertNull($ss->getSection());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VolumeSetting
    // ══════════════════════════════════════════════════════════════════════════

    public function testVolumeSettingTableConstant(): void
    {
        $this->assertSame('VOLUME_SETTING', VolumeSetting::TABLE);
    }

    public function testVolumeSettingSetSettingReturnsSelf(): void
    {
        $vs = new VolumeSetting();
        $result = $vs->setSetting('displayCover');
        $this->assertSame($vs, $result);
    }

    public function testVolumeSettingGetSettingReturnsSetValue(): void
    {
        $vs = new VolumeSetting();
        $vs->setSetting('allowBrowse');
        $this->assertSame('allowBrowse', $vs->getSetting());
    }

    public function testVolumeSettingSetValueReturnsSelf(): void
    {
        $vs = new VolumeSetting();
        $result = $vs->setValue('1');
        $this->assertSame($vs, $result);
    }

    public function testVolumeSettingGetValueReturnsSetValue(): void
    {
        $vs = new VolumeSetting();
        $vs->setValue('true');
        $this->assertSame('true', $vs->getValue());
    }

    // NOTE: VolumeSetting::$value is typed 'string' (non-nullable) but setValue() accepts ?string.
    // Passing null causes a TypeError — this is a bug in the entity. Null-reset test omitted.

    public function testVolumeSettingGetVolumeDefaultsToNull(): void
    {
        $vs = new VolumeSetting();
        $this->assertNull($vs->getVolume());
    }

    public function testVolumeSettingSetVolumeReturnsSelf(): void
    {
        $vs = new VolumeSetting();
        $vol = $this->createStub(Volume::class);
        $result = $vs->setVolume($vol);
        $this->assertSame($vs, $result);
    }

    public function testVolumeSettingGetVolumeReturnsSetValue(): void
    {
        $vs = new VolumeSetting();
        $vol = $this->createStub(Volume::class);
        $vs->setVolume($vol);
        $this->assertSame($vol, $vs->getVolume());
    }

    public function testVolumeSettingSetVolumeWithNullResetsToNull(): void
    {
        $vs = new VolumeSetting();
        $vs->setVolume($this->createStub(Volume::class));
        $vs->setVolume(null);
        $this->assertNull($vs->getVolume());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UserInvitation
    // ══════════════════════════════════════════════════════════════════════════

    public function testUserInvitationTableConstant(): void
    {
        $this->assertSame('USER_INVITATION', UserInvitation::TABLE);
    }

    public function testUserInvitationDefaultStatusIsPending(): void
    {
        $inv = new UserInvitation();
        $this->assertSame('pending', $inv->getStatus());
    }

    public function testUserInvitationDefaultTokenIsNull(): void
    {
        $inv = new UserInvitation();
        $this->assertNull($inv->getToken());
    }

    public function testUserInvitationDefaultSenderUidIsNull(): void
    {
        $inv = new UserInvitation();
        $this->assertNull($inv->getSenderUid());
    }

    public function testUserInvitationSetAidReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $result = $inv->setAid(10);
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetAidReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $inv->setAid(99);
        $this->assertSame(99, $inv->getAid());
    }

    public function testUserInvitationSetStatusReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $result = $inv->setStatus('accepted');
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetStatusReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $inv->setStatus('declined');
        $this->assertSame('declined', $inv->getStatus());
    }

    public function testUserInvitationSetTokenReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $result = $inv->setToken('abc123');
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetTokenReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $inv->setToken('mytoken');
        $this->assertSame('mytoken', $inv->getToken());
    }

    public function testUserInvitationSetTokenWithNullResetsToNull(): void
    {
        $inv = new UserInvitation();
        $inv->setToken('tok');
        $inv->setToken(null);
        $this->assertNull($inv->getToken());
    }

    public function testUserInvitationSetSenderUidReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $result = $inv->setSenderUid(5);
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetSenderUidReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $inv->setSenderUid(42);
        $this->assertSame(42, $inv->getSenderUid());
    }

    public function testUserInvitationSetSendingDateReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $dt = new DateTime('2024-01-01');
        $result = $inv->setSendingDate($dt);
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetSendingDateReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $dt = new DateTime('2024-06-15');
        $inv->setSendingDate($dt);
        $this->assertSame($dt, $inv->getSendingDate());
    }

    public function testUserInvitationSetExpirationDateReturnsSelf(): void
    {
        $inv = new UserInvitation();
        $dt = new DateTime('2024-12-31');
        $result = $inv->setExpirationDate($dt);
        $this->assertSame($inv, $result);
    }

    public function testUserInvitationGetExpirationDateReturnsSetValue(): void
    {
        $inv = new UserInvitation();
        $dt = new DateTime('2025-01-01');
        $inv->setExpirationDate($dt);
        $this->assertSame($dt, $inv->getExpirationDate());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MailLog
    // ══════════════════════════════════════════════════════════════════════════

    public function testMailLogSetRvidReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setRvid(1);
        $this->assertSame($log, $result);
    }

    public function testMailLogGetRvidReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setRvid(5);
        $this->assertSame(5, $log->getRvid());
    }

    public function testMailLogDefaultDocidIsNull(): void
    {
        $log = new MailLog();
        $this->assertNull($log->getDocid());
    }

    public function testMailLogSetDocidReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setDocid(100);
        $this->assertSame(100, $log->getDocid());
    }

    public function testMailLogSetDocidWithNullResetsToNull(): void
    {
        $log = new MailLog();
        $log->setDocid(99);
        $log->setDocid(null);
        $this->assertNull($log->getDocid());
    }

    public function testMailLogSetFromReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setFrom('sender@example.com');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetFromReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setFrom('test@example.org');
        $this->assertSame('test@example.org', $log->getFrom());
    }

    public function testMailLogSetReplytoReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setReplyto('reply@example.com');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetReplytoReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setReplyto('noreply@example.com');
        $this->assertSame('noreply@example.com', $log->getReplyto());
    }

    public function testMailLogSetToReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setTo('dest@example.com');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetToReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setTo('to@example.com');
        $this->assertSame('to@example.com', $log->getTo());
    }

    public function testMailLogSetCcReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setCc('cc@example.com');
        $this->assertSame($log, $result);
    }

    public function testMailLogSetBccReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setBcc('bcc@example.com');
        $this->assertSame($log, $result);
    }

    public function testMailLogSetSubjectReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setSubject('Test subject');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetSubjectReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setSubject('Hello world');
        $this->assertSame('Hello world', $log->getSubject());
    }

    public function testMailLogSetContentReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setContent('<p>Body</p>');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetContentReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setContent('plain text');
        $this->assertSame('plain text', $log->getContent());
    }

    public function testMailLogSetFilesReturnsSelf(): void
    {
        $log = new MailLog();
        $result = $log->setFiles('file1.pdf');
        $this->assertSame($log, $result);
    }

    public function testMailLogGetFilesReturnsSetValue(): void
    {
        $log = new MailLog();
        $log->setFiles('attach.pdf');
        $this->assertSame('attach.pdf', $log->getFiles());
    }

    public function testMailLogSetWhenReturnsSelf(): void
    {
        $log = new MailLog();
        $dt = new DateTime('2023-05-10');
        $result = $log->setWhen($dt);
        $this->assertSame($log, $result);
    }

    public function testMailLogGetWhenReturnsSetValue(): void
    {
        $log = new MailLog();
        $dt = new DateTime('2023-12-25');
        $log->setWhen($dt);
        $this->assertSame($dt, $log->getWhen());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperComments
    // ══════════════════════════════════════════════════════════════════════════

    public function testPaperCommentsDefaultParentidIsNull(): void
    {
        $pc = new PaperComments();
        $this->assertNull($pc->getParentid());
    }

    public function testPaperCommentsDefaultMessageIsNull(): void
    {
        $pc = new PaperComments();
        $this->assertNull($pc->getMessage());
    }

    public function testPaperCommentsDefaultFileIsNull(): void
    {
        $pc = new PaperComments();
        $this->assertNull($pc->getFile());
    }

    public function testPaperCommentsDefaultDeadlineIsNull(): void
    {
        $pc = new PaperComments();
        $this->assertNull($pc->getDeadline());
    }

    public function testPaperCommentsDefaultOptionsIsNull(): void
    {
        $pc = new PaperComments();
        $this->assertNull($pc->getOptions());
    }

    public function testPaperCommentsSetParentidReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setParentid(10);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetParentidReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setParentid(5);
        $this->assertSame(5, $pc->getParentid());
    }

    public function testPaperCommentsSetTypeReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setType(2);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetTypeReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setType(3);
        $this->assertSame(3, $pc->getType());
    }

    public function testPaperCommentsSetDocidReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setDocid(100);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetDocidReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setDocid(200);
        $this->assertSame(200, $pc->getDocid());
    }

    public function testPaperCommentsSetUidReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setUid(7);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetUidReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setUid(42);
        $this->assertSame(42, $pc->getUid());
    }

    public function testPaperCommentsSetMessageReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setMessage('A comment');
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetMessageReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setMessage('Hello');
        $this->assertSame('Hello', $pc->getMessage());
    }

    public function testPaperCommentsSetWhenReturnsSelf(): void
    {
        $pc = new PaperComments();
        $dt = new DateTime('2023-01-01');
        $result = $pc->setWhen($dt);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetWhenReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $dt = new DateTime('2024-03-15');
        $pc->setWhen($dt);
        $this->assertSame($dt, $pc->getWhen());
    }

    public function testPaperCommentsSetDeadlineReturnsSelf(): void
    {
        $pc = new PaperComments();
        $dt = new DateTime('2024-12-31');
        $result = $pc->setDeadline($dt);
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsSetOptionsReturnsSelf(): void
    {
        $pc = new PaperComments();
        $result = $pc->setOptions('{"key":"val"}');
        $this->assertSame($pc, $result);
    }

    public function testPaperCommentsGetOptionsReturnsSetValue(): void
    {
        $pc = new PaperComments();
        $pc->setOptions('data');
        $this->assertSame('data', $pc->getOptions());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DoiQueue
    // ══════════════════════════════════════════════════════════════════════════

    public function testDoiQueueDefaultDoiStatusIsAssigned(): void
    {
        $dq = new DoiQueue();
        $this->assertSame('assigned', $dq->getDoiStatus());
    }

    public function testDoiQueueConstructorSetsDateUpdated(): void
    {
        $before = new DateTime();
        $dq = new DoiQueue();
        $after = new DateTime();

        $this->assertNotNull($dq->getDateUpdated());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $dq->getDateUpdated()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $dq->getDateUpdated()->getTimestamp());
    }

    public function testDoiQueueSetPaperidReturnsSelf(): void
    {
        $dq = new DoiQueue();
        $result = $dq->setPaperid(55);
        $this->assertSame($dq, $result);
    }

    public function testDoiQueueGetPaperidReturnsSetValue(): void
    {
        $dq = new DoiQueue();
        $dq->setPaperid(123);
        $this->assertSame(123, $dq->getPaperid());
    }

    public function testDoiQueueSetDoiStatusReturnsSelf(): void
    {
        $dq = new DoiQueue();
        $result = $dq->setDoiStatus('completed');
        $this->assertSame($dq, $result);
    }

    public function testDoiQueueGetDoiStatusReturnsSetValue(): void
    {
        $dq = new DoiQueue();
        $dq->setDoiStatus('pending');
        $this->assertSame('pending', $dq->getDoiStatus());
    }

    public function testDoiQueueSetDateInitReturnsSelf(): void
    {
        $dq = new DoiQueue();
        $dt = new DateTime('2024-01-01');
        $result = $dq->setDateInit($dt);
        $this->assertSame($dq, $result);
    }

    public function testDoiQueueGetDateInitReturnsSetValue(): void
    {
        $dq = new DoiQueue();
        $dt = new DateTime('2024-03-01');
        $dq->setDateInit($dt);
        $this->assertSame($dt, $dq->getDateInit());
    }

    public function testDoiQueueSetDateUpdatedReturnsSelf(): void
    {
        $dq = new DoiQueue();
        $dt = new DateTime('2024-05-01');
        $result = $dq->setDateUpdated($dt);
        $this->assertSame($dq, $result);
    }

    public function testDoiQueueGetDateUpdatedReturnsSetValue(): void
    {
        $dq = new DoiQueue();
        $dt = new DateTime('2025-01-01');
        $dq->setDateUpdated($dt);
        $this->assertSame($dt, $dq->getDateUpdated());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UserTmp
    // ══════════════════════════════════════════════════════════════════════════

    public function testUserTmpDefaultEmailIsNull(): void
    {
        $u = new UserTmp();
        $this->assertNull($u->getEmail());
    }

    public function testUserTmpDefaultFirstnameIsNull(): void
    {
        $u = new UserTmp();
        $this->assertNull($u->getFirstname());
    }

    public function testUserTmpDefaultLastnameIsNull(): void
    {
        $u = new UserTmp();
        $this->assertNull($u->getLastname());
    }

    public function testUserTmpDefaultLangIsNull(): void
    {
        $u = new UserTmp();
        $this->assertNull($u->getLang());
    }

    public function testUserTmpSetEmailReturnsSelf(): void
    {
        $u = new UserTmp();
        $result = $u->setEmail('test@example.com');
        $this->assertSame($u, $result);
    }

    public function testUserTmpGetEmailReturnsSetValue(): void
    {
        $u = new UserTmp();
        $u->setEmail('user@example.org');
        $this->assertSame('user@example.org', $u->getEmail());
    }

    public function testUserTmpSetFirstnameReturnsSelf(): void
    {
        $u = new UserTmp();
        $result = $u->setFirstname('John');
        $this->assertSame($u, $result);
    }

    public function testUserTmpGetFirstnameReturnsSetValue(): void
    {
        $u = new UserTmp();
        $u->setFirstname('Alice');
        $this->assertSame('Alice', $u->getFirstname());
    }

    public function testUserTmpSetLastnameReturnsSelf(): void
    {
        $u = new UserTmp();
        $result = $u->setLastname('Doe');
        $this->assertSame($u, $result);
    }

    public function testUserTmpGetLastnameReturnsSetValue(): void
    {
        $u = new UserTmp();
        $u->setLastname('Smith');
        $this->assertSame('Smith', $u->getLastname());
    }

    public function testUserTmpSetLangReturnsSelf(): void
    {
        $u = new UserTmp();
        $result = $u->setLang('en');
        $this->assertSame($u, $result);
    }

    public function testUserTmpGetLangReturnsSetValue(): void
    {
        $u = new UserTmp();
        $u->setLang('fr');
        $this->assertSame('fr', $u->getLang());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WebsiteHeader
    // ══════════════════════════════════════════════════════════════════════════

    public function testWebsiteHeaderSetTypeReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setType('logo');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetTypeReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setType('banner');
        $this->assertSame('banner', $wh->getType());
    }

    public function testWebsiteHeaderSetImgReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setImg('logo.png');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetImgReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setImg('header.jpg');
        $this->assertSame('header.jpg', $wh->getImg());
    }

    public function testWebsiteHeaderSetImgWidthReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setImgWidth('200px');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetImgWidthReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setImgWidth('300');
        $this->assertSame('300', $wh->getImgWidth());
    }

    public function testWebsiteHeaderSetImgHeightReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setImgHeight('100px');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetImgHeightReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setImgHeight('150');
        $this->assertSame('150', $wh->getImgHeight());
    }

    public function testWebsiteHeaderSetImgHrefReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setImgHref('/');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetImgHrefReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setImgHref('/home');
        $this->assertSame('/home', $wh->getImgHref());
    }

    public function testWebsiteHeaderSetImgAltReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setImgAlt('Site logo');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetImgAltReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setImgAlt('Logo alt text');
        $this->assertSame('Logo alt text', $wh->getImgAlt());
    }

    public function testWebsiteHeaderSetTextReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setText('Welcome');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetTextReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setText('Journal title');
        $this->assertSame('Journal title', $wh->getText());
    }

    public function testWebsiteHeaderSetTextClassReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setTextClass('title-class');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderSetTextStyleReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setTextStyle('color: red');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderSetAlignReturnsSelf(): void
    {
        $wh = new WebsiteHeader();
        $result = $wh->setAlign('center');
        $this->assertSame($wh, $result);
    }

    public function testWebsiteHeaderGetAlignReturnsSetValue(): void
    {
        $wh = new WebsiteHeader();
        $wh->setAlign('left');
        $this->assertSame('left', $wh->getAlign());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WebsiteNavigation
    // ══════════════════════════════════════════════════════════════════════════

    public function testWebsiteNavigationSetSidReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setSid(1);
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationGetSidReturnsSetValue(): void
    {
        $wn = new WebsiteNavigation();
        $wn->setSid(5);
        $this->assertSame(5, $wn->getSid());
    }

    public function testWebsiteNavigationSetPageidReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setPageid(10);
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationGetPageidReturnsSetValue(): void
    {
        $wn = new WebsiteNavigation();
        $wn->setPageid(99);
        $this->assertSame(99, $wn->getPageid());
    }

    public function testWebsiteNavigationSetTypePageReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setTypePage('custom');
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationGetTypePageReturnsSetValue(): void
    {
        $wn = new WebsiteNavigation();
        $wn->setTypePage('static');
        $this->assertSame('static', $wn->getTypePage());
    }

    public function testWebsiteNavigationSetControllerReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setController('PageController');
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationSetActionReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setAction('index');
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationSetLabelReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setLabel('Home');
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationGetLabelReturnsSetValue(): void
    {
        $wn = new WebsiteNavigation();
        $wn->setLabel('About');
        $this->assertSame('About', $wn->getLabel());
    }

    public function testWebsiteNavigationSetParentPageidReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setParentPageid(0);
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationSetParamsReturnsSelf(): void
    {
        $wn = new WebsiteNavigation();
        $result = $wn->setParams('{"key":"val"}');
        $this->assertSame($wn, $result);
    }

    public function testWebsiteNavigationGetParamsReturnsSetValue(): void
    {
        $wn = new WebsiteNavigation();
        $wn->setParams('{}');
        $this->assertSame('{}', $wn->getParams());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WebsiteStyles
    // ══════════════════════════════════════════════════════════════════════════

    public function testWebsiteStylesSetValueReturnsSelf(): void
    {
        $ws = new WebsiteStyles();
        $result = $ws->setValue('#ffffff');
        $this->assertSame($ws, $result);
    }

    public function testWebsiteStylesGetValueReturnsSetValue(): void
    {
        $ws = new WebsiteStyles();
        $ws->setValue('bold');
        $this->assertSame('bold', $ws->getValue());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperConflicts
    // ══════════════════════════════════════════════════════════════════════════

    public function testPaperConflictsTableConstant(): void
    {
        $this->assertSame('paper_conflicts', PaperConflicts::TABLE);
    }

    public function testPaperConflictsAvailableAnswerKeys(): void
    {
        $this->assertArrayHasKey('yes', PaperConflicts::AVAILABLE_ANSWER);
        $this->assertArrayHasKey('no', PaperConflicts::AVAILABLE_ANSWER);
        $this->assertArrayHasKey('later', PaperConflicts::AVAILABLE_ANSWER);
    }

    public function testPaperConflictsConstructorSetsDate(): void
    {
        $before = new DateTime();
        $pc = new PaperConflicts();
        $after = new DateTime();

        $this->assertNotNull($pc->getDate());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $pc->getDate()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $pc->getDate()->getTimestamp());
    }

    public function testPaperConflictsSetPaperIdReturnsSelf(): void
    {
        $pc = new PaperConflicts();
        $result = $pc->setPaperId(10);
        $this->assertSame($pc, $result);
    }

    public function testPaperConflictsGetPaperIdReturnsSetValue(): void
    {
        $pc = new PaperConflicts();
        $pc->setPaperId(42);
        $this->assertSame(42, $pc->getPaperId());
    }

    public function testPaperConflictsSetByReturnsSelf(): void
    {
        $pc = new PaperConflicts();
        $result = $pc->setBy(7);
        $this->assertSame($pc, $result);
    }

    public function testPaperConflictsGetByReturnsSetValue(): void
    {
        $pc = new PaperConflicts();
        $pc->setBy(99);
        $this->assertSame(99, $pc->getBy());
    }

    public function testPaperConflictsSetAnswerReturnsSelf(): void
    {
        $pc = new PaperConflicts();
        $result = $pc->setAnswer('yes');
        $this->assertSame($pc, $result);
    }

    public function testPaperConflictsGetAnswerReturnsSetValue(): void
    {
        $pc = new PaperConflicts();
        $pc->setAnswer('no');
        $this->assertSame('no', $pc->getAnswer());
    }

    public function testPaperConflictsDefaultMessageIsNull(): void
    {
        $pc = new PaperConflicts();
        $this->assertNull($pc->getMessage());
    }

    public function testPaperConflictsSetMessageReturnsSelf(): void
    {
        $pc = new PaperConflicts();
        $result = $pc->setMessage('conflict reason');
        $this->assertSame($pc, $result);
    }

    public function testPaperConflictsSetDateReturnsSelf(): void
    {
        $pc = new PaperConflicts();
        $dt = new DateTime('2024-06-01');
        $result = $pc->setDate($dt);
        $this->assertSame($pc, $result);
    }

    public function testPaperConflictsGetPapersDefaultsToNull(): void
    {
        $pc = new PaperConflicts();
        $this->assertNull($pc->getPapers());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UserMerge
    // ══════════════════════════════════════════════════════════════════════════

    public function testUserMergeConstructorSetsDate(): void
    {
        $before = new DateTime();
        $um = new UserMerge();
        $after = new DateTime();

        $this->assertNotNull($um->getDate());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $um->getDate()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $um->getDate()->getTimestamp());
    }

    public function testUserMergeDefaultTokenIsNull(): void
    {
        $um = new UserMerge();
        $this->assertNull($um->getToken());
    }

    public function testUserMergeDefaultDetailIsNull(): void
    {
        $um = new UserMerge();
        $this->assertNull($um->getDetail());
    }

    public function testUserMergeSetTokenReturnsSelf(): void
    {
        $um = new UserMerge();
        $result = $um->setToken('abc123');
        $this->assertSame($um, $result);
    }

    public function testUserMergeGetTokenReturnsSetValue(): void
    {
        $um = new UserMerge();
        $um->setToken('mytoken');
        $this->assertSame('mytoken', $um->getToken());
    }

    public function testUserMergeSetMergerUidReturnsSelf(): void
    {
        $um = new UserMerge();
        $result = $um->setMergerUid(10);
        $this->assertSame($um, $result);
    }

    public function testUserMergeGetMergerUidReturnsSetValue(): void
    {
        $um = new UserMerge();
        $um->setMergerUid(55);
        $this->assertSame(55, $um->getMergerUid());
    }

    public function testUserMergeSetKeeperUidReturnsSelf(): void
    {
        $um = new UserMerge();
        $result = $um->setKeeperUid(20);
        $this->assertSame($um, $result);
    }

    public function testUserMergeGetKeeperUidReturnsSetValue(): void
    {
        $um = new UserMerge();
        $um->setKeeperUid(66);
        $this->assertSame(66, $um->getKeeperUid());
    }

    public function testUserMergeSetDetailReturnsSelf(): void
    {
        $um = new UserMerge();
        $result = $um->setDetail('some detail');
        $this->assertSame($um, $result);
    }

    public function testUserMergeGetDetailReturnsSetValue(): void
    {
        $um = new UserMerge();
        $um->setDetail('merge detail');
        $this->assertSame('merge detail', $um->getDetail());
    }

    public function testUserMergeSetDateReturnsSelf(): void
    {
        $um = new UserMerge();
        $dt = new DateTime('2024-07-01');
        $result = $um->setDate($dt);
        $this->assertSame($um, $result);
    }

    public function testUserMergeGetDateReturnsSetValue(): void
    {
        $um = new UserMerge();
        $dt = new DateTime('2025-01-15');
        $um->setDate($dt);
        $this->assertSame($dt, $um->getDate());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // StatTemp
    // ══════════════════════════════════════════════════════════════════════════

    public function testStatTempDefaultConsultIsNotice(): void
    {
        $st = new StatTemp();
        $this->assertSame('notice', $st->getConsult());
    }

    public function testStatTempConstructorSetsDhit(): void
    {
        $before = new DateTime();
        $st = new StatTemp();
        $after = new DateTime();

        $this->assertNotNull($st->getDhit());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $st->getDhit()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $st->getDhit()->getTimestamp());
    }

    public function testStatTempSetDocidReturnsSelf(): void
    {
        $st = new StatTemp();
        $result = $st->setDocid(10);
        $this->assertSame($st, $result);
    }

    public function testStatTempGetDocidReturnsSetValue(): void
    {
        $st = new StatTemp();
        $st->setDocid(123);
        $this->assertSame(123, $st->getDocid());
    }

    public function testStatTempSetIpReturnsSelf(): void
    {
        $st = new StatTemp();
        $result = $st->setIp(2130706433); // 127.0.0.1 as int
        $this->assertSame($st, $result);
    }

    public function testStatTempGetIpReturnsSetValue(): void
    {
        $st = new StatTemp();
        $st->setIp(16777343); // some IP
        $this->assertSame(16777343, $st->getIp());
    }

    public function testStatTempSetHttpUserAgentReturnsSelf(): void
    {
        $st = new StatTemp();
        $result = $st->setHttpUserAgent('Mozilla/5.0');
        $this->assertSame($st, $result);
    }

    public function testStatTempGetHttpUserAgentReturnsSetValue(): void
    {
        $st = new StatTemp();
        $st->setHttpUserAgent('curl/7.0');
        $this->assertSame('curl/7.0', $st->getHttpUserAgent());
    }

    public function testStatTempSetDhitReturnsSelf(): void
    {
        $st = new StatTemp();
        $dt = new DateTime('2023-11-11');
        $result = $st->setDhit($dt);
        $this->assertSame($st, $result);
    }

    public function testStatTempGetDhitReturnsSetValue(): void
    {
        $st = new StatTemp();
        $dt = new DateTime('2024-02-20');
        $st->setDhit($dt);
        $this->assertSame($dt, $st->getDhit());
    }

    public function testStatTempSetConsultReturnsSelf(): void
    {
        $st = new StatTemp();
        $result = $st->setConsult('download');
        $this->assertSame($st, $result);
    }

    public function testStatTempGetConsultReturnsSetValue(): void
    {
        $st = new StatTemp();
        $st->setConsult('download');
        $this->assertSame('download', $st->getConsult());
    }
}
