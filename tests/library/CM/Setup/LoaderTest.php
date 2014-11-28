<?php

class CM_Provision_LoaderTest extends CMTest_TestCase {

    public function testGetScriptList() {
        $script1 = $this->mockObject('CM_Provision_Script_Abstract');
        $script2 = $this->mockObject('CM_Provision_Script_Abstract');
        $script2->mockMethod('getRunLevel')->set(10);
        $script3 = $this->mockObject('CM_Provision_Script_Abstract');
        $script3->mockMethod('getRunLevel')->set(1);

        $loader = new CM_Provision_Loader();
        $loader->registerScript($script1);
        $loader->registerScript($script2);
        $loader->registerScript($script3);

        $scriptList = CMTest_TH::callProtectedMethod($loader, '_getScriptList');
        $expected = [$script3, $script1, $script2];
        $this->assertSame($expected, $scriptList);
    }

    public function testLoad() {
        $serviceManager = new CM_Service_Manager();
        $outputStream = new CM_OutputStream_Null();

        $script = $this->mockObject('CM_Provision_Script_Abstract');
        $script->mockMethod('shouldBeLoaded')->set(true);
        $loadMethod = $script->mockMethod('load')->set(function (CM_Service_Manager $manager, $output) use ($serviceManager, $outputStream) {
            $this->assertSame($serviceManager, $manager);
            $this->assertSame($outputStream, $output);
        });
        /** @var CM_Provision_Script_Abstract $script */

        $loader = new CM_Provision_Loader($outputStream);
        $loader->setServiceManager($serviceManager);
        $loader->registerScript($script);
        $loader->load();
        $this->assertSame(1, $loadMethod->getCallCount());
    }

    public function testGetScriptListUnloadable() {
        $classScriptUnloadable = $this->mockClass('CM_Provision_Script_Abstract', ['CM_Provision_Script_UnloadableInterface']);

        $script1 = $this->mockObject('CM_Provision_Script_Abstract');
        $script2 = $classScriptUnloadable->newInstance();
        $script3 = $classScriptUnloadable->newInstance();

        $loader = $this->mockObject('CM_Provision_Loader');
        $loader->mockMethod('_getScriptList')->set([$script1, $script2, $script3]);
        $this->assertSame([$script3, $script2], CMTest_TH::callProtectedMethod($loader, '_getScriptListUnloadable'));
    }

    public function testUnload() {
        $serviceManager = new CM_Service_Manager();
        $outputStream = new CM_OutputStream_Null();

        $script = $this->mockClass('CM_Provision_Script_Abstract', ['CM_Provision_Script_UnloadableInterface'])->newInstance();
        $script->mockMethod('shouldBeUnloaded')->set(true);
        $unloadMethod = $script->mockMethod('unload')->set(function (CM_Service_Manager $manager, $output) use ($serviceManager, $outputStream) {
            $this->assertSame($serviceManager, $manager);
            $this->assertSame($outputStream, $output);
        });
        /** @var CM_Provision_Script_Abstract $script */

        $loader = new CM_Provision_Loader($outputStream);
        $loader->setServiceManager($serviceManager);
        $loader->registerScript($script);
        $loader->unload();
        $this->assertSame(1, $unloadMethod->getCallCount());
    }
}
