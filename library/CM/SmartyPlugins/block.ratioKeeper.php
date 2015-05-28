<?php

require_once 'function.tag.php';

function smarty_block_ratioKeeper($params, $content, Smarty_Internal_Template $template, $open) {
    if ($open) {
        return '';
    } else {
        if (isset($params['width']) && isset($params['height'])) {
            $width = (int) $params['width'];
            $height = (int) $params['height'];
        } elseif ($params['ratio']) {
            $ratio = $params['ratio'] * 100;
            $width = 100;
            $height = $width * $ratio;
        } else {
            $width = $height = 1;
        }

        $image = imagecreate($width, $height);
        ob_start();
        imagecolorallocate($image, 255, 255, 255);
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        $imageSrc = 'data:image/png;base64,' . base64_encode($imageData);

        $output = '<div class="ratioKeeper">';
        $output .= '<img class="ratioKeeper-ratio" src="' . $imageSrc . '">';

        $contentAttrs = isset($params['contentAttrs']) ? $params['contentAttrs'] : [];
        if (isset($contentAttrs['class'])) {
            $contentAttrs['class'] .= ' ratioKeeper-content';
        } else {
            $contentAttrs['class'] = 'ratioKeeper-content';
        }

        $contentAttrs['el'] = 'div';
        $contentAttrs['content'] = $content;
        $output .= smarty_function_tag($contentAttrs, $template);

        $output .= '</div>';
        return $output;
    }
}
