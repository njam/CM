<?php

class CM_Paging_Ip_BlockedTest extends CMTest_TestCase {

	public function testAdd() {
		$ip = '127.0.0.1';
		$ip2 = '127.0.0.2';
		$paging = new CM_Paging_Ip_Blocked();
		$paging->add(ip2long($ip));
		$this->assertEquals(1, $paging->getCount());
		$entry = $paging->getItem(0);
		$this->assertTrue($paging->contains(ip2long($ip)));
		CMTest_TH::timeDaysForward(2);
		$paging->add(ip2long($ip2));
		CM_Cache_Local::flush();
		$paging->_change();
		$this->assertEquals(2, $paging->getCount());
		CMTest_TH::timeDaysForward(2);
		CM_Paging_Ip_Blocked::deleteOlder(3 * 86400);
		CM_Cache_Local::flush();
		$paging->_change();
		$this->assertEquals(1, $paging->getCount());
		CMTest_TH::timeDaysForward(2);
		CM_Paging_Ip_Blocked::deleteOlder(3 * 86400);
		CM_Cache_Local::flush();
		$this->assertEquals(1, $paging->getCount());
		$paging->_change();
		$this->assertEquals(0, $paging->getCount());
	}
}
