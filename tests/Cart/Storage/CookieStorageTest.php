<?php
/**
 * @author: Johnny Theill <j.theill@gmail.com>
 * @date: 07-02-2015 01:20
 */

namespace Theill11\Tests\Cart\Storage;

use Symfony\Component\HttpFoundation\Cookie;
use Theill11\Cart\Item;
use Theill11\Cart\Storage\CookieStorage;

class CookieStorageTest extends \PHPUnit_Framework_TestCase
{
    public $name = '_test-name';

    protected function getRequestStackMock($request)
    {
        $mock = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
        $mock->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);
        return $mock;
    }

    protected function getRequestMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
    }

    protected function getRequestMockConfigured()
    {
        $bagMock = $this->getBagMock();
        $bagMock
            ->expects($this->exactly(4))
            ->method('get')
            ->with($this->name)
            ->willReturnOnConsecutiveCalls(
                serialize([1 => $this->getItem(1)]),
                serialize([2 => $this->getItem(2)]),
                serialize([1 => new \stdClass()]),
                serialize([1 => $this->getItem(1), 2 => $this->getItem(2)])
            );
        $mock = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $mock->cookies = $bagMock;
        return $mock;
    }

    protected function getResponseMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->getMock();
    }

    protected function getBagMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpFoundation\ParameterBag')->getMock();
    }

    protected function getHeaderMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpFoundation\ResponseHeaderBag')->getMock();
    }

    protected function getItem($id)
    {
        switch ($id){
            case 1:
                return new Item(['id' => 1, 'name' => 'Test item 1', 'quantity' => 10, 'price' => 123.45]);
            case 2:
                return new Item(['id' => 2, 'name' => 'Test item 2', 'quantity' => 10, 'price' => 123.45]);
        }
        return false;
    }

    public function testCanInstantiate()
    {
        $requestMock = $this->getRequestMock();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);
        $this->assertInstanceOf('Theill11\Cart\Storage\CookieStorage', $storage);
    }

    public function testGet()
    {
        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $this->assertNull($storage->get(null));
        $this->assertNull($storage->get(0));
        $this->assertNull($storage->get(1));
        $this->assertInstanceOf('Theill11\Cart\ItemInterface', $storage->get(2));
    }

    public function testRemove()
    {
        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $headerMock = $this->getHeaderMock();
        $headerMock
            ->expects($this->once())
            ->method('setCookie')
            ->with($this->isInstanceOf('Symfony\Component\HttpFoundation\Cookie'));
        $responseMock->headers = $headerMock;
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $this->assertFalse($storage->remove(null));
        $this->assertFalse($storage->remove(1));
        $this->assertFalse($storage->remove(1));
        $this->assertTrue($storage->remove(1));
    }

    public function testHas()
    {
        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $this->assertFalse($storage->has(null));
        $this->assertFalse($storage->has(1));
        $this->assertFalse($storage->has(1));
        $this->assertTrue($storage->has(1));
    }

    public function testSet()
    {
        $callOne = function ($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $this->getItem(1)]);
        };

        $callTwo = function($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([2 => $this->getItem(2), 1 => $this->getItem(1)]);
        };

        $callThree = function($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $this->getItem(1)]);
        };

        $callFour = function($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $this->getItem(1), 2 => $this->getItem(2)]);
        };

        $headerMock = $this->getHeaderMock();
        $headerMock
            ->expects($this->exactly(4))
            ->method('setCookie')
            ->withConsecutive(
                [$this->callback($callOne)],
                [$this->callback($callTwo)],
                [$this->callback($callThree)],
                [$this->callback($callFour)]
            );

        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $responseMock->headers = $headerMock;
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $storage->set($this->getItem(1));
        $storage->set($this->getItem(1));
        $storage->set($this->getItem(1));
        $storage->set($this->getItem(1));
    }

    public function testAdd()
    {
        $callOne = function ($subject) {
            $item1 = $this->getItem(1);
            $item1->setQuantity(20);
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $item1]);
        };

        $callTwo = function ($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([2 => $this->getItem(2), 1 => $this->getItem(1)]);
        };

        $callThree = function($subject) {
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $this->getItem(1)]);
        };

        $callFour = function($subject) {
            $item1 = $this->getItem(1);
            $item1->setQuantity(20);
            return
                $subject instanceof Cookie
                && $subject->getName() === $this->name
                && $subject->getValue() === serialize([1 => $item1, 2 => $this->getItem(2)]);
        };

        $headerMock = $this->getHeaderMock();
        $headerMock
            ->expects($this->exactly(4))
            ->method('setCookie')
            ->withConsecutive(
                [$this->callback($callOne)],
                [$this->callback($callTwo)],
                [$this->callback($callThree)],
                [$this->callback($callFour)]
            );

        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $responseMock->headers = $headerMock;
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $storage->add($this->getItem(1));
        $storage->add($this->getItem(1));
        $storage->add($this->getItem(1));
        // add Item with an id which already is added
        $storage->add($this->getItem(1));
    }

    public function testAll()
    {
        $requestMock = $this->getRequestMockConfigured();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $this->assertEquals(1, count($storage->all()));
        $this->assertEquals(1, count($storage->all()));
        $this->assertEquals(0, count($storage->all()));
        $this->assertEquals(2, count($storage->all()));
    }

    public function testClear()
    {
        $headerMock = $this->getHeaderMock();
        $headerMock
            ->expects($this->exactly(1))
            ->method('clearCookie')
            ->with($this->name) ;

        $requestMock = $this->getRequestMock();
        $requestStackMock = $this->getRequestStackMock($requestMock);
        $responseMock = $this->getResponseMock();
        $responseMock->headers = $headerMock;
        $storage = new CookieStorage($requestStackMock, $responseMock, $this->name);

        $storage->clear();
    }
}
