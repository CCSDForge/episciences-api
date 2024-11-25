<?php

namespace App\Resource;

use App\Traits\ToolsTrait;
use Symfony\Component\Serializer\Annotation\Groups;

class SolrDoc
{
    use ToolsTrait;

    public const COMMON_GROUPS = ['read:Browse:Authors:fullName', 'read:Search'];
    public const DEFAULT_LANGUAGE = 'en';
    #[groups(self::COMMON_GROUPS)]
    private array $author_fullname_fs;
    #[groups(self::COMMON_GROUPS)]
    private array $author_fullname_s;
    #[groups(self::COMMON_GROUPS)]
    private array $keyword_t;
    #[groups(self::COMMON_GROUPS)]
    private int $docid;
    #[groups(self::COMMON_GROUPS)]
    private int $paperid;
    #[groups(self::COMMON_GROUPS)]
    private string $doi_s;
    #[groups(self::COMMON_GROUPS)]
    private string $language_s;
    #[groups(self::COMMON_GROUPS)]
    private string $identifier_s;
    #[groups(self::COMMON_GROUPS)]
    private int $version_td;
    #[groups(self::COMMON_GROUPS)]
    private string $es_submission_date_tdate;
    #[groups(self::COMMON_GROUPS)]
    private string $es_publication_date_tdate;
    #[groups(self::COMMON_GROUPS)]
    private string $es_doc_url_s;
    #[groups(self::COMMON_GROUPS)]
    private string $es_pdf_url_s;
    #[groups(self::COMMON_GROUPS)]
    private string $publication_date_tdate;
    #[groups(self::COMMON_GROUPS)]
    private string $publication_date_year_fs;
    #[groups(self::COMMON_GROUPS)]
    private string $publication_date_month_fs;
    #[groups(self::COMMON_GROUPS)]
    private string $publication_date_day_fs;
    #[groups(self::COMMON_GROUPS)]
    private int $revue_id_i;
    #[groups(self::COMMON_GROUPS)]
    private string $revue_code_t;
    #[groups(self::COMMON_GROUPS)]
    private string $revue_title_s;
    #[groups(self::COMMON_GROUPS)]
    private array $paper_title_t;
    #[groups(self::COMMON_GROUPS)]
    private string $fr_paper_title_t;
    #[groups(self::COMMON_GROUPS)]
    private string $en_paper_title_t;
    #[groups(self::COMMON_GROUPS)]
    private array $abstract_t;
    #[groups(self::COMMON_GROUPS)]
    private string $fr_abstract_t;
    #[groups(self::COMMON_GROUPS)]
    private string $en_abstract_t;
    #[groups(self::COMMON_GROUPS)]
    private int $volume_id_i;
    private int $section_id_i;
    #[groups(self::COMMON_GROUPS)]
    private int $volume_status_i;
    #[groups(self::COMMON_GROUPS)]
    private string $volume_fs;
    #[groups(self::COMMON_GROUPS)]
    private string $en_volume_title_t;
    #[groups(self::COMMON_GROUPS)]
    private array $volume_title_t;
    #[groups(self::COMMON_GROUPS)]
    private string $fr_volume_title_t;
    #[groups(self::COMMON_GROUPS)]
    private string $indexing_date_tdate;
    #[groups(self::COMMON_GROUPS)]
    private int $_version_;

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }


    /**
     * @return array
     */
    public function getAuthorFullnameFs(): array
    {
        return $this->author_fullname_fs;
    }

    /**
     * @param array $author_fullname_fs
     */
    public function setAuthorFullnameFs(array $author_fullname_fs): void
    {
        $this->author_fullname_fs = $author_fullname_fs;
    }

    /**
     * @return array
     */
    public function getAuthorFullnameS(): array
    {
        return $this->author_fullname_s;
    }

    /**
     * @param array $author_fullname_s
     */
    public function setAuthorFullnameS(array $author_fullname_s): void
    {
        $this->author_fullname_s = $author_fullname_s;
    }

    /**
     * @return array
     */
    public function getKeywordT(): array
    {
        return $this->keyword_t;
    }

    /**
     * @param array $keyword_t
     */
    public function setKeywordT(array $keyword_t): void
    {
        $this->keyword_t = $keyword_t;
    }

    /**
     * @return int
     */
    public function getDocid(): int
    {
        return $this->docid;
    }

    /**
     * @param int $docid
     */
    public function setDocid(int $docid): void
    {
        $this->docid = $docid;
    }

    /**
     * @return int
     */
    public function getPaperid(): int
    {
        return $this->paperid;
    }

    /**
     * @param int $paperid
     */
    public function setPaperid(int $paperid): void
    {
        $this->paperid = $paperid;
    }

    /**
     * @return string
     */
    public function getDoiS(): string
    {
        return $this->doi_s;
    }

    /**
     * @param string $doi_s
     */
    public function setDoiS(string $doi_s): void
    {
        $this->doi_s = $doi_s;
    }

    /**
     * @return string
     */
    public function getLanguageS(): string
    {
        return $this->language_s;
    }

    /**
     * @param string $language_s
     */
    public function setLanguageS(string $language_s = self::DEFAULT_LANGUAGE): void
    {
        if ($language_s !== 'false') {
            $this->language_s = $language_s;
        } else {
            $this->language_s = self::DEFAULT_LANGUAGE;
        }
    }

    /**
     * @return string
     */
    public function getIdentifierS(): string
    {
        return $this->identifier_s;
    }

    /**
     * @param string $identifier_s
     */
    public function setIdentifierS(string $identifier_s): void
    {
        $this->identifier_s = $identifier_s;
    }

    /**
     * @return int
     */
    public function getVersionTd(): int
    {
        return $this->version_td;
    }

    /**
     * @param int $version_td
     */
    public function setVersionTd(int $version_td): void
    {
        $this->version_td = $version_td;
    }

    /**
     * @return string
     */
    public function getEsSubmissionDateTdate(): string
    {
        return $this->es_submission_date_tdate;
    }

    /**
     * @param string $es_submission_date_tdate
     */
    public function setEsSubmissionDateTdate(string $es_submission_date_tdate): void
    {
        $this->es_submission_date_tdate = $es_submission_date_tdate;
    }

    /**
     * @return string
     */
    public function getEsPublicationDateTdate(): string
    {
        return $this->es_publication_date_tdate;
    }

    /**
     * @param string $es_publication_date_tdate
     */
    public function setEsPublicationDateTdate(string $es_publication_date_tdate): void
    {
        $this->es_publication_date_tdate = $es_publication_date_tdate;
    }

    /**
     * @return string
     */
    public function getEsDocUrlS(): string
    {
        return $this->es_doc_url_s;
    }

    /**
     * @param string $es_doc_url_s
     */
    public function setEsDocUrlS(string $es_doc_url_s): void
    {
        $this->es_doc_url_s = $es_doc_url_s;
    }

    /**
     * @return string
     */
    public function getEsPdfUrlS(): string
    {
        return $this->es_pdf_url_s;
    }

    /**
     * @param string $es_pdf_url_s
     */
    public function setEsPdfUrlS(string $es_pdf_url_s): void
    {
        $this->es_pdf_url_s = $es_pdf_url_s;
    }

    /**
     * @return string
     */
    public function getPublicationDateTdate(): string
    {
        return $this->publication_date_tdate;
    }

    /**
     * @param string $publication_date_tdate
     */
    public function setPublicationDateTdate(string $publication_date_tdate): void
    {
        $this->publication_date_tdate = $publication_date_tdate;
    }

    /**
     * @return string
     */
    public function getPublicationDateYearFs(): string
    {
        return $this->publication_date_year_fs;
    }

    /**
     * @param string $publication_date_year_fs
     */
    public function setPublicationDateYearFs(string $publication_date_year_fs): void
    {
        $this->publication_date_year_fs = $publication_date_year_fs;
    }

    /**
     * @return string
     */
    public function getPublicationDateMonthFs(): string
    {
        return $this->publication_date_month_fs;
    }

    /**
     * @param string $publication_date_month_fs
     */
    public function setPublicationDateMonthFs(string $publication_date_month_fs): void
    {
        $this->publication_date_month_fs = $publication_date_month_fs;
    }

    /**
     * @return string
     */
    public function getPublicationDateDayFs(): string
    {
        return $this->publication_date_day_fs;
    }

    /**
     * @param string $publication_date_day_fs
     */
    public function setPublicationDateDayFs(string $publication_date_day_fs): void
    {
        $this->publication_date_day_fs = $publication_date_day_fs;
    }

    /**
     * @return int
     */
    public function getRevueIdI(): int
    {
        return $this->revue_id_i;
    }

    /**
     * @param int $revue_id_i
     */
    public function setRevueIdI(int $revue_id_i): void
    {
        $this->revue_id_i = $revue_id_i;
    }

    /**
     * @return string
     */
    public function getRevueCodeT(): string
    {
        return $this->revue_code_t;
    }

    /**
     * @param string $revue_code_t
     */
    public function setRevueCodeT(string $revue_code_t): void
    {
        $this->revue_code_t = $revue_code_t;
    }

    /**
     * @return string
     */
    public function getRevueTitleS(): string
    {
        return $this->revue_title_s;
    }

    /**
     * @param string $revue_title_s
     */
    public function setRevueTitleS(string $revue_title_s): void
    {
        $this->revue_title_s = $revue_title_s;
    }


    public function getPaperTitleT(): array
    {
        return $this->paper_title_t;
    }


    public function setPaperTitleT(array $paper_title_t): void
    {
        $this->paper_title_t = $paper_title_t;
    }

    public function getAbstractT(): array
    {
        return $this->abstract_t;
    }


    public function setAbstractT(array $abstract_t): void
    {
        $this->abstract_t = $abstract_t;
    }

    /**
     * @return int
     */
    public function getVolumeIdI(): int
    {
        return $this->volume_id_i;
    }

    /**
     * @param int $volume_id_i
     */
    public function setVolumeIdI(int $volume_id_i): void
    {
        $this->volume_id_i = $volume_id_i;
    }

    /**
     * @return int
     */
    public function getSectionIdI(): int
    {
        return $this->section_id_i;
    }

    /**
     * @param int $section_id_i
     */
    public function setSectionIdI(int $section_id_i): void
    {
        $this->section_id_i = $section_id_i;
    }

    /**
     * @return int
     */
    public function getVolumeStatusI(): int
    {
        return $this->volume_status_i;
    }

    /**
     * @param int $volume_status_i
     */
    public function setVolumeStatusI(int $volume_status_i): void
    {
        $this->volume_status_i = $volume_status_i;
    }

    /**
     * @return string
     */
    public function getVolumeFs(): string
    {
        return $this->volume_fs;
    }

    /**
     * @param string $volume_fs
     */
    public function setVolumeFs(string $volume_fs): void
    {
        $this->volume_fs = $volume_fs;
    }

    /**
     * @return string
     */
    public function getEnVolumeTitleT(): string
    {
        return $this->en_volume_title_t;
    }

    /**
     * @param string $en_volume_title_t
     */
    public function setEnVolumeTitleT(string $en_volume_title_t): void
    {
        $this->en_volume_title_t = $en_volume_title_t;
    }

    /**
     * @return array
     */
    public function getVolumeTitleT(): array
    {
        return $this->volume_title_t;
    }

    /**
     * @param array $volume_title_t
     */
    public function setVolumeTitleT(array $volume_title_t): void
    {
        $this->volume_title_t = $volume_title_t;
    }

    /**
     * @return string
     */
    public function getFrVolumeTitleT(): string
    {
        return $this->fr_volume_title_t;
    }

    /**
     * @param string $fr_volume_title_t
     */
    public function setFrVolumeTitleT(string $fr_volume_title_t): void
    {
        $this->fr_volume_title_t = $fr_volume_title_t;
    }

    /**
     * @return string
     */
    public function getIndexingDateTdate(): string
    {
        return $this->indexing_date_tdate;
    }

    /**
     * @param string $indexing_date_tdate
     */
    public function setIndexingDateTdate(string $indexing_date_tdate): void
    {
        $this->indexing_date_tdate = $indexing_date_tdate;
    }

    /**
     * @return string
     */
    public function getFrPaperTitleT(): string
    {
        return $this->fr_paper_title_t;
    }

    /**
     * @param string $fr_paper_title_t
     */
    public function setFrPaperTitleT(string $fr_paper_title_t): void
    {
        $this->fr_paper_title_t = $fr_paper_title_t;
    }

    /**
     * @return string
     */
    public function getEnPaperTitleT(): string
    {
        return $this->en_paper_title_t;
    }

    /**
     * @param string $en_paper_title_t
     */
    public function setEnPaperTitleT(string $en_paper_title_t): void
    {
        $this->en_paper_title_t = $en_paper_title_t;
    }

    /**
     * @return string
     */
    public function getFrAbstractT(): string
    {
        return $this->fr_abstract_t;
    }

    /**
     * @param string $fr_abstract_t
     */
    public function setFrAbstractT(string $fr_abstract_t): void
    {
        $this->fr_abstract_t = $fr_abstract_t;
    }

    /**
     * @return string
     */
    public function getEnAbstractT(): string
    {
        return $this->en_abstract_t;
    }

    /**
     * @param string $en_abstract_t
     */
    public function setEnAbstractT(string $en_abstract_t): void
    {
        $this->en_abstract_t = $en_abstract_t;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->_version_;
    }

    /**
     * @param int $version_
     */
    public function setVersion(int $version_): void
    {
        $this->_version_ = $version_;
    }

    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = self::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

}