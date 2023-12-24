<?php
/**
 * rep2expack - ���X�t�B���^�����O�N���X
 */

// {{{ ResFilter

class ResFilter
{
    // {{{ constants

    const FIELD_NUMBER = 'num';
    const FIELD_HOLE = 'hole';
    const FIELD_NAME = 'name';
    const FIELD_MAIL = 'mail';
    const FIELD_DATE = 'date';
    const FIELD_ID = 'id';
    const FIELD_MESSAGE = 'msg';
    const FIELD_DEFAULT = self::FIELD_MESSAGE;

    const METHOD_OR = 'or';
    const METHOD_AND = 'and';
    const METHOD_JUST = 'just';
    const METHOD_REGEX = 'regex';
    const METHOD_DEFAULT = self::METHOD_OR;

    const MATCH_ON = 'on';
    const MATCH_OFF = 'off';
    const MATCH_DEFAULT = self::MATCH_ON;

    const INCLUDE_NONE = 0;
    const INCLUDE_REFERENCES = 1;
    const INCLUDE_REFERENCED = 2;
    const INCLUDE_BOTH = 3; // INCLUDE_REFERENCES | INCLUDE_REFERENCED
    const INCLUDE_DEFAULT= self::INCLUDE_NONE;

    // }}}
    // {{{ properties

    static private $_instance = null;

    static protected $_fields = array(
        self::FIELD_NUMBER => '���X�ԍ�',
        self::FIELD_HOLE => '�S��',
        self::FIELD_MESSAGE => '�{��',
        self::FIELD_NAME => '���O',
        self::FIELD_MAIL => '���[��',
        self::FIELD_DATE => '���t',
        self::FIELD_ID => 'ID',
    );

    static protected $_methods = array(
        self::METHOD_OR => '�����ꂩ',
        self::METHOD_AND => '���ׂ�',
        self::METHOD_JUST => '���̂܂�',
        self::METHOD_REGEX => '���K�\��',
    );

    static protected $_matches = array(
        self::MATCH_ON => '�܂�',
        self::MATCH_OFF => '�܂܂Ȃ�',
    );

    static protected $_includes = array(
        self::INCLUDE_NONE => '',
        self::INCLUDE_REFERENCES => '+�Q�ƃ��X',
        self::INCLUDE_REFERENCED => '+�t�Q�ƃ��X',
        self::INCLUDE_BOTH => '+�Q��+�t�Q��',
    );

    private $_word_fm;
    private $_words_fm;
    private $_words_num;

    public $word;
    public $field;
    public $method;
    public $match;
    public $include;

    public $hits;
    public $last_hit_resnum;
    public $range;

    // }}}
    // {{{ getFilter()

    /**
     * configure()�ō��ꂽ�I�u�W�F�N�g��Ԃ�
     *
     * @param void
     * @return ResFilter|null
     */
    static public function getFilter()
    {
        return self::$_instance;
    }

    // }}}
    // {{{ getQuery()

    /**
     * configure()�ō��ꂽ�I�u�W�F�N�g�ɐݒ肳��Ă���p�����[�^����
     * HTTP GET�p�N�G�����쐬����
     *
     * @param string $separator
     * @return string
     */
    static public function getQuery($separator = '&')
    {
        $filter = self::$_instance;
        if ($filter === null) {
            $params = array(
                'field'   => self::FIELD_DEFAULT,
                'method'  => self::METHOD_DEFAULT,
                'match'   => self::MATCH_DEFAULT,
                'include' => self::INCLUDE_DEFAULT,
            );
        } else {
            $params = array(
                'field'   => $filter->field,
                'method'  => $filter->method,
                'match'   => $filter->match,
                'include' => $filter->include,
            );
            if ($filter->hasWord()) {
                $params['word'] = $filter->word;
            }
        }

        return http_build_query(array('rf' => $params), '', $separator);
    }

    // }}}
    // {{{ getWord()

    /**
     * configure()�ō��ꂽ�I�u�W�F�N�g�ɐݒ肳��Ă���L�[���[�h��Ԃ�
     *
     * @param callback $callback
     * @param array $params
     * @return string|null
     */
    static public function getWord($callback = null, array $params = array())
    {
        $filter = self::$_instance;
        if ($filter === null || $filter->word === null) {
            return null;
        }
        if ($callback !== null && is_callable($callback)) {
            array_unshift($params, $filter->word);
            return call_user_func_array($callback, $params);
        }
        return $filter->word;
    }

    // }}}
    // {{{ configure()

    /**
     * �A�z�z�񂩂�ResFilter�I�u�W�F�N�g���쐬���A�ێ�����
     *
     * @param array $params
     * @return ResFilter
     */
    static public function configure(array $params)
    {
        $word    = $params['word'] ?? null;
        mb_convert_variables('CP932', 'UTF-8,CP932', $word);
        $field   = $params['field'] ?? null;
        $method  = $params['method'] ?? null;
        $match   = $params['method'] ?? null;
        $include = $params['include'] ?? null;

        self::$_instance = new ResFilter($word, $field, $method, $match, $include);

        return self::$_instance;
    }

    // }}}
    // {{{ restore()

    /**
     * �R���X�g���N�^
     *
     * @param void
     * @return ResFilter|null
     */
    static public function restore()
    {
        global $_conf;

        $cachefile = $_conf['pref_dir'] . '/p2_res_filter.txt';
        $res_filter_cont = FileCtl::file_read_contents($cachefile);
        if ($res_filter_cont) {
            $filter = self::configure(unserialize($res_filter_cont));
        } else {
            $filter = null;
        }

        return $filter;
    }

    // }}}
    // {{{ __construct()

    /**
     * �R���X�g���N�^
     *
     * @param string $word
     * @param string $field
     * @param string $method
     * @param string $match
     * @param int $include
     */
    public function __construct($word,
                                $field   = self::FIELD_DEFAULT,
                                $method  = self::METHOD_DEFAULT,
                                $match   = self::MATCH_DEFAULT,
                                $include = self::INCLUDE_DEFAULT)
    {
        global $_conf;

        $this->hits = 0;

        if ($field !== null && array_key_exists($field, self::$_fields)) {
            $this->field = $field;
        } else {
            $this->field = self::FIELD_DEFAULT;
        }
        if ($method !== null && array_key_exists($method, self::$_methods)) {
            $this->method = $method;
        } else {
            $this->method = self::METHOD_DEFAULT;
        }
        if ($match !== null && array_key_exists($match, self::$_matches)) {
            $this->match = $match;
        } else {
            $this->match = self::MATCH_DEFAULT;
        }
        if ($include !== null && array_key_exists($include, self::$_includes)) {
            $this->include = $include;
        } else {
            $this->include = self::INCLUDE_DEFAULT;
        }

        $this->setWord($word);
        $this->range = null;
    }

    // }}}
    // {{{ save()

    /**
     * �t�B���^�����O�ݒ���t�@�C���ɕۑ�����
     *
     * @param void
     * @return void
     */
    public function save()
    {
        global $_conf;

        $cachefile = $_conf['pref_dir'] . '/p2_res_filter.txt';
        $res_filter_cont = serialize(array(
            'field'   => $this->field,
            'method'  => $this->method,
            'match'   => $this->match,
            'include' => $this->include,
        ));
        FileCtl::file_write_contents($cachefile, $res_filter_cont);
    }

    // }}}
    // {{{ getTarget()

    /**
     * �t�B���^�����O�̃^�[�Q�b�g�𓾂�
     *
     * @param string $ares
     * @param int $resnum
     * @param string $name
     * @param string $mail
     * @param string $date_id
     * @param string $msg
     * @return string
     */
    public function getTarget($ares, $resnum, $name, $mail, $date_id, $msg)
    {
        switch ($this->field) {
            case self::FIELD_NUMBER:
                $target = (string)$resnum;
                break;
            case self::FIELD_NAME:
                $target = $name;
                break;
            case self::FIELD_MAIL:
                $target = $mail;
                break;
            case self::FIELD_DATE:
                $target = preg_replace('| ?ID:[0-9A-Za-z/.+?]+.*$|', '', $date_id);
                break;
            case self::FIELD_ID:
                if ($target = preg_replace('|^.*ID:([0-9A-Za-z/.+?]+).*$|', '$1', $date_id)) {
                    break;
                } else {
                    return '';
                }
            case self::FIELD_MESSAGE:
                $target = $msg;
                break;
            case self::FIELD_HOLE:
            default:
                $target = "{$resnum}<>{$ares}";
        }

        $target = @strip_tags($target, '<>');

        return $target;
    }

    // }}}
    // {{{ getPattern()

    /**
     * �}�b�`���O�p�p�^�[����Ԃ�
     * �L�[���[�h�̃n�C���C�g�p��z��
     *
     * @param void
     * @return string|null
     */
    public function getPattern()
    {
        return $this->_word_fm;
    }

    // }}}
    // {{{ hasWord()

    /**
     * �L�[���[�h���ݒ肳��Ă��邩�ǂ����𔻒肷��
     *
     * @param void
     * @return boolean
     */
    public function hasWord()
    {
        return ($this->word === null) ? false : true;
    }

    // }}}
    // {{{ setWord()

    /**
     * �L�[���[�h��ݒ肷��
     *
     * @param string $word
     * @return void
     */
    public function setWord($word)
    {
        global $_conf;

        if (is_string($word) && strlen($word) > 0) {
            if ($this->method == 'regex' && substr_count($word, '.') == strlen($word)) {
                $word_fm = null;
            } else {
                $word_fm = StrCtl::wordForMatch($word, $this->method);
                if (strlen($word_fm) == 0) {
                    $word_fm = null;
                } elseif ($this->method == self::METHOD_JUST || $this->method == self::METHOD_REGEX) {
                    $words_fm = array($word_fm);
                } elseif (P2_MBREGEX_AVAILABLE == 1) {
                    $word_fm = mb_ereg_replace('\\s+', '|', $word_fm);
                    $words_fm = mb_split('\\s+', $word_fm);
                } else {
                    $word_fm = preg_replace('/\\s+/', '|', $word_fm);
                    $words_fm = preg_split('/\\s+/', $word_fm);
                }
            }
        } else {
            $word_fm = null;
        }

        if ($word_fm !== null) {
            $this->word = $word;
            $this->_word_fm = $word_fm;
            $this->_words_fm = $words_fm;
            $this->_words_num = count($words_fm);
        } else {
            $this->word = null;
            $this->_word_fm = null;
            $this->_words_fm = null;
            $this->_words_num = 0;
        }
    }

    // }}}
    // {{{ setRange()

    /**
     * �\���͈͂�ݒ肷��
     *
     * @param int $perPage
     * @param int $page
     * @return void
     */
    public function setRange($perPage, $page = 1)
    {
        $perPage = max(0, (int)$perPage);
        if ($perPage === 0) {
            $this->range = null;
        } else {
            $page = max(1, (int)$page);
            $this->range = array(
                'page'  => $page,
                'start' => ($page - 1) * $perPage + 1,
                'to'    => $page * $perPage,
            );
        }
    }

    // }}}
    // {{{ apply()

    /**
     * ���X�t�B���^��K�p����
     *
     * @param ShowThread $aShowThread
     * @return array
     */
    public function apply(ShowThread $aShowThread)
    {
        $aThread = $aShowThread->thread;
        $failure = ($this->match == self::MATCH_ON) ? false : true;
        $count = count($aThread->datlines);
        $datlines = array_fill(0, $count, null);
        $hit_nums = array();
//        $res_nums = array();
        $check_refs = ($this->include & self::INCLUDE_REFERENCES) ? true : false;
        $check_refed = ($this->include & self::INCLUDE_REFERENCED) ? true : false;

        // {{{ 1�p�X�� (�}�b�`���O�ƎQ�ƃ��X���o)

        if ($this->field == self::FIELD_NUMBER &&
            $this->method == self::METHOD_JUST &&
            $this->match == self::MATCH_ON)
        {
            // ���X�ԍ����S��v�͓��ʈ���
            $n = (int)$this->word;
            if ($n > 0 && $n <= $count) {
                $i = $n - 1;
                $ares = $aThread->datlines[$i];
                $datlines[$i] = $ares;
                $hit_nums[] = $i;
                if ($check_refs || $check_refed) {
                    list($name, $mail, $date_id, $msg) = $aThread->explodeDatLine($ares);
                    foreach ($aShowThread->checkQuoteResNums($n, $name, $msg, $check_refs, $check_refed, false) as $rn) {
                        $ri = $rn - 1;
                        if ($datlines[$ri] === null) {
                            $datlines[$ri] = $aThread->datlines[$ri];
                            $hit_nums[] = $ri;
                        }
                    }
                }
/*
                if ($check_refed) {
                    $res_nums[] = $n;
                }
*/
            }
        } else {
            // �ʏ�̃}�b�`���O
            foreach ($aThread->datlines as $i => $ares) {
                $n = $i + 1;
                list($name, $mail, $date_id, $msg) = $aThread->explodeDatLine($ares);
                if (($id = $aThread->ids[$n]) !== null) {
                    $date_id = str_replace($aThread->idp[$n] . $id, "ID:$id", $date_id);
                }
    
                $target = $this->getTarget($ares, $n, $name, $mail, $date_id, $msg);
                if (!$target) {
                    continue;
                }
    
                if ($this->_match($target, $n, $failure)) {
                    if ($datlines[$i] === null) {
                        $datlines[$i] = $ares;
                        $hit_nums[] = $i;
                    }
                    if ($check_refs || $check_refed) {
                        foreach ($aShowThread->checkQuoteResNums($n, $name, $msg, $check_refs, $check_refed, false) as $rn) {
                            $ri = $rn - 1;
                            if ($datlines[$ri] === null) {
                                $datlines[$ri] = $aThread->datlines[$ri];
                                $hit_nums[] = $ri;
                            }
                        }
                    }
/*
                    if ($check_refed) {
                        $res_nums[] = $n;
                    }
*/
                }
            }
        }

        // }}}
        // {{{ 2�p�X�� (�}�b�`�������X�ւ̎Q��)

/*
        if (count($res_nums)) {
            $pattern = ShowThread::getAnchorRegex(
                '%prefix%(.+%delimiter%)?(?:' . implode('|', $res_nums) . ')(?!\\d|%range_delimiter%)'
            );
            foreach ($aThread->datlines as $i => $ares) {
                if ($datlines[$i] === null) {
                    list(, , , $msg) = $aThread->explodeDatLine($ares);
                    if (StrCtl::filterMatch($pattern, $msg, false)) {
                        $datlines[$i] = $aThread->datlines[$i];
                        $hit_nums[] = $i;
                    }
                }
            }
        }
*/

        // }}}

        $hits = count($hit_nums);
        if ($hits) {
            $this->hits += $hits;
            $this->last_hit_resnum = max($hit_nums);
        }

        return $datlines;
    }

    // }}}
    // {{{ _match()

    /**
     * �}�b�`����
     *
     * @param string $target
     * @param int $resnum
     * @param boolean $failure
     * @return boolean
     */
    private function _match($target, $resnum, $failure)
    {
        if ($this->method == self::METHOD_AND) {
            $hits = 0;
            foreach ($this->_words_fm as $pattern) {
                if (StrCtl::filterMatch($pattern, $target) == $failure) {
                    if ($failure === false) {
                        return false;
                    } else {
                        $hits++;
                    }
                }
            }
            if ($hits == $this->_words_num) {
                return false;
            }
        } else {
            if (StrCtl::filterMatch($this->_word_fm, $target) == $failure) {
                return false;
            }
        }

        return true;
    }

    // }}}
}

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
