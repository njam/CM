<?php

class CM_Site_AbstractTest extends CMTest_TestCase {

    /** @var CM_Site_Abstract */
    private $_site;

    public function setUp() {
        $this->_site = $this->getMockSite(null, [
            'url'    => 'http://www.foo.com',
            'urlCdn' => 'http://www.cdn.com',
        ], [
            'name'                    => 'Foo',
            'emailAddress'            => 'foo@foo.com',
            'robotIndexingDisallowed' => false,
        ]);
    }

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testGetConfig() {
        $config = $this->_site->getConfig();
        $this->assertSame('http://www.foo.com', $config->url);
        $this->assertSame('http://www.cdn.com', $config->urlCdn);
    }

    public function testGetUrl() {
        $this->assertSame('http://www.foo.com/', (string) $this->_site->getUrl());
    }

    public function testGetUrlCdn() {
        $this->assertSame('http://www.cdn.com/', (string) $this->_site->getUrlCdn());
    }

    public function testGetWebFontLoaderConfig() {
        $this->assertSame(null, $this->_site->getWebFontLoaderConfig());
    }

    public function testIsUrlMatch() {
        $site = $this->getMockSite(null, [
            'url'    => 'http://www.my-site.com',
            'urlCdn' => 'http://cdn.my-site.com',
        ]);
        $this->assertSame(true, $site->isUrlMatch('my-site.com', '/'));
        $this->assertSame(true, $site->isUrlMatch('my-site.com', '/foo'));
        $this->assertSame(true, $site->isUrlMatch('www.my-site.com', '/foo'));
        $this->assertSame(true, $site->isUrlMatch('cdn.my-site.com', '/foo'));
        $this->assertSame(false, $site->isUrlMatch('something.my-site.com', '/foo'));
        $this->assertSame(false, $site->isUrlMatch('something.com', '/foo'));
    }

    public function testIsUrlMatchWithPath() {
        $site = $this->getMockSite(null, [
            'url' => 'http://www.my-site.com/foo',
        ]);
        $this->assertSame(false, $site->isUrlMatch('my-site.com', '/'));
        $this->assertSame(true, $site->isUrlMatch('my-site.com', '/foo'));
        $this->assertSame(true, $site->isUrlMatch('my-site.com', '/foo/bar'));
        $this->assertSame(true, $site->isUrlMatch('www.my-site.com', '/foo'));
        $this->assertSame(false, $site->isUrlMatch('something.my-site.com', '/foo'));
    }

    public function testEquals() {
        $siteFoo = $this->mockClass(CM_Site_Abstract::class);
        $siteFoo->mockMethod('getUrlString')->set('http://foo.com');
        /** @var CM_Site_Abstract $siteFoo1 */
        $siteFoo1 = $siteFoo->newInstance();
        /** @var CM_Site_Abstract $siteFoo2 */
        $siteFoo2 = $siteFoo->newInstance();

        $siteBar = $this->mockClass(CM_Site_Abstract::class);
        $siteBar->mockMethod('getUrlString')->set('http://foo.com');
        /** @var CM_Site_Abstract $siteBar1 */
        $siteBar1 = $siteBar->newInstance();

        $this->assertSame(true, $siteFoo2->equals($siteFoo1));
        $this->assertSame(true, $siteFoo1->equals($siteFoo2));
        $this->assertSame(false, $siteFoo1->equals(null));

        $this->assertSame(false, $siteFoo1->equals($siteBar1));
        $this->assertSame(false, $siteBar1->equals($siteFoo1));

        $comparableMock = $this->mockInterface(CM_Comparable::class);
        $this->assertSame(false, $siteFoo1->equals($comparableMock->newInstanceWithoutConstructor()));
    }

    public function testEqualsDifferentUrl() {
        $siteClass = $this->mockClass(CM_Site_Abstract::class);

        /** @var CM_Site_Abstract|\Mocka\AbstractClassTrait $site1 */
        $site1 = $siteClass->newInstance();
        $site1->mockMethod('getUrlString')->set('http://my-site1.com');

        /** @var CM_Site_Abstract|\Mocka\AbstractClassTrait $site2 */
        $site2 = $siteClass->newInstance();
        $site2->mockMethod('getUrlString')->set('http://my-site2.com');

        $this->assertSame(false, $site1->equals($site2));
    }

    public function testModelGettersSetters() {
        $site = $this->_site;
        $this->assertSame('foo@foo.com', $site->getEmailAddress());
        $this->assertSame('Foo', $site->getName());

        $site->setEmailAddress('bar@bar.com');
        $site->setName('Bar');
        $this->assertSame('bar@bar.com', $site->getEmailAddress());
        $this->assertSame('Bar', $site->getName());

        $this->assertSame(false, $site->isRobotIndexingDisallowed());
        $site->setRobotIndexingDisallowed();
        $this->assertSame(true, $site->isRobotIndexingDisallowed());
        $site->setRobotIndexingDisallowed(false);
        $this->assertSame(false, $site->isRobotIndexingDisallowed());
        $site->setRobotIndexingDisallowed(true);
        $this->assertSame(true, $site->isRobotIndexingDisallowed());
    }

    public function testDefault() {
        $site = $this->_site;
        $this->assertSame(false, $site->getDefault());
        $site->setDefault();
        $this->assertSame(true, $site->getDefault());

        $site2 = $this->getMockSite();
        $site2->setDefault();
        CMTest_TH::reinstantiateModel($site);
        CMTest_TH::reinstantiateModel($site2);
        $this->assertSame(false, $site->getDefault());
        $this->assertSame(true, $site2->getDefault());
    }

    public function testFactoryFromType() {
        $siteMock = $this->getMockSite();
        $site = CM_Site_Abstract::factoryFromType($siteMock->getId(), $siteMock->getType());
        $this->assertInstanceOf(CM_Site_Abstract::class, $site);
        $this->assertEquals($siteMock, $site);
    }

    public function testFactoryFromId() {
        $siteMock = $this->getMockSite();
        $site = CM_Site_Abstract::factoryFromId($siteMock->getId());
        $this->assertInstanceOf(CM_Site_Abstract::class, $site);
        $this->assertEquals($siteMock, $site);

        $exception = $this->catchException(function () {
            CM_Site_Abstract::factoryFromId('507f1f77bcf86cd799439011');
        });
        $this->assertInstanceOf(CM_Exception_Nonexistent::class, $exception);
        $this->assertSame('Site doesn\'t exist', $exception->getMessage());
    }

    public function testFactoryFromModelData() {
        $mongoDb = $this->getServiceManager()->getMongoDb();
        $defaultSite = (new CM_Site_SiteFactory())->getDefaultSite();
        $id = $defaultSite->getId();
        $modelData = $mongoDb->findOne(CM_Site_Abstract::getTableName(), ['_id' => CM_MongoDb_Client::getObjectId($id)]);
        $createdSite = CM_Site_Abstract::factoryFromModelData($modelData);
        $this->assertEquals($defaultSite, $createdSite);
    }

    public function testFactory() {
        $siteMock = $this->getMockSite();
        $site = $siteMock::factory();
        $this->assertInstanceOf(CM_Site_Abstract::class, $site);
        $this->assertEquals($siteMock, $site);
    }

    public function testCacheInvalidation() {
        $defaultSite = (new CM_Site_SiteFactory())->getDefaultSite();
        $siteList = (new CM_Paging_Site_All())->getItems();
        $siteEmailList = \Functional\map($siteList, function (CM_Site_Abstract $site) {
            return $site->getEmailAddress();
        });
        $this->assertEquals(['default@default.dev','foo@foo.com'], $siteEmailList);
        $defaultSite->setEmailAddress('changed@example.com');

        $siteList = (new CM_Paging_Site_All())->getItems();
        $siteEmailList = \Functional\map($siteList, function (CM_Site_Abstract $site) {
            return $site->getEmailAddress();
        });
        $this->assertEquals(['changed@example.com','foo@foo.com'], $siteEmailList);
    }
}
