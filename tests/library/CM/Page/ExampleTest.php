<?php

class CM_Page_ExampleTest extends CMTest_TestCase {

    public function testAccessible() {
        $debugBackup = CM_Bootloader::getInstance()->isDebug();
        $page = new CM_Page_Example();

        CM_Bootloader::getInstance()->setDebug(true);
        $this->_renderPage($page);

        CM_Bootloader::getInstance()->setDebug(false);
        $this->assertPageNotRenderable($page);

        CM_Bootloader::getInstance()->setDebug($debugBackup);
    }

    public function testTidy() {
        $debugBackup = CM_Bootloader::getInstance()->isDebug();

        CM_Bootloader::getInstance()->setDebug(true);
        $page = $this->_createPage('CM_Page_Example');
        $html = $this->_renderPage($page);
        $this->assertTidy($html, false);

        CM_Bootloader::getInstance()->setDebug($debugBackup);
    }
}
