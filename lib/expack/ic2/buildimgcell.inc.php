<?php
/* vim: set fileencoding=cp932 ai et ts=4 sw=4 sts=4 fdm=marker: */
/* mi: charset=Shift_JIS */

function buildImgCell(&$img)
{
    global $_conf, $ini, $icdb, $thumb;

    // ��������URI�͐܂�Ԃ�
    if (strlen($img['uri']) > 45) {
        $w = explode("\n", wordwrap($img['uri'], 45, "\n", 1));
        $w = array_map('htmlspecialchars', $w);
        $add['uri_w'] = implode('<br />', $w);
    } else {
        $add['uri_w'] = $img['uri'];
    }

    if ($img['mime'] == 'clamscan/infected') {

        // �E�B���X�Ɋ������Ă����t�@�C���̂Ƃ�
        $add['src'] = './img/x04.png';
        $add['thumb'] = './img/x04.png';
        $add['t_width'] = 32;
        $add['t_height'] = 32;

    } else {

        // �\�[�X�ƃT���l�C���̃p�X���擾
        $add['src'] = $thumb->srcPath($icdb->size, $icdb->md5, $icdb->mime);
        $add['thumb'] = $thumb->thumbPath($icdb->size, $icdb->md5, $icdb->mime);

        // �T���l�C���̏c���̑傫�����v�Z
        @preg_match('/(\d+)x(\d+)/', $thumb->calc($icdb->width, $icdb->height), $m);
        $add['t_width'] = $m[1];
        $add['t_height'] = $m[2];

    }

    // �\�[�X�̃t�@�C���T�C�Y�̏����𐮂���
    if ($img['size'] > 1024 * 1024) {
        $add['size_f'] = number_format($img['size'] / (1024 * 1024), 1) . 'MB';
    } elseif ($img['size'] > 1024) {
        $add['size_f'] = number_format($img['size'] / 1024, 1) . 'KB';
    } else {
        $add['size_f'] = $img['size'] . 'B';
    }

    // ���t�̏����𐮂���
    $add['date'] = date('Y-m-d (D) H:i:s', $img['time']);

    return $add;
}

function ic2_read_exif($path)
{
    $exif = @exif_read_data($path, '', TRUE, FALSE);
    if ($exif) {
        // �o�C�i���ŁA�������f�[�^�T�C�Y���傫���v�f���폜
        if (isset($exif['MakerNote'])) {
            unset($exif['MakerNote']);
        }
        if (isset($exif['EXIF']) && isset($exif['EXIF']['MakerNote'])) {
            unset($exif['EXIF']['MakerNote']);
        }
        return $exif;
    } else {
        return NULL;
    }
}