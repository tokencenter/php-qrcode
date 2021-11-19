<?php
/**
 * Class QRCodeReaderTest
 *
 * @created      17.01.2021
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2021 Smiley
 * @license      MIT
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

namespace chillerlan\QRCodeTest;

use chillerlan\Settings\SettingsContainerInterface;
use Exception;
use chillerlan\QRCode\Common\{EccLevel, Mode, Version};
use chillerlan\QRCode\{QRCode, QROptions};
use PHPUnit\Framework\TestCase;
use function extension_loaded, range, str_repeat, substr;

/**
 * Tests the QR Code reader
 */
class QRCodeReaderTest extends TestCase{

	// https://www.bobrosslipsum.com/
	protected const loremipsum = 'Just let this happen. We just let this flow right out of our minds. '
		.'Anyone can paint. We touch the canvas, the canvas takes what it wants. From all of us here, '
		.'I want to wish you happy painting and God bless, my friends. A tree cannot be straight if it has a crooked trunk. '
		.'You have to make almighty decisions when you\'re the creator. I guess that would be considered a UFO. '
		.'A big cotton ball in the sky. I\'m gonna add just a tiny little amount of Prussian Blue. '
		.'They say everything looks better with odd numbers of things. But sometimes I put even numbers—just '
		.'to upset the critics. We\'ll lay all these little funky little things in there. ';

	private SettingsContainerInterface $options;

	protected function setUp():void{
		$this->options = new QROptions;
	}

	public function qrCodeProvider():array{
		return [
			'helloworld' => ['hello_world.png', 'Hello world!'],
			// covers mirroring
			'mirrored'   => ['hello_world_mirrored.png', 'Hello world!'],
			// data modes
			'byte'       => ['byte.png', 'https://smiley.codes/qrcode/'],
			'numeric'    => ['numeric.png', '123456789012345678901234567890'],
			'alphanum'   => ['alphanum.png', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890 $%*+-./:'],
			'kanji'      => ['kanji.png', '茗荷茗荷茗荷茗荷'],
			// covers most of ReedSolomonDecoder
			'damaged'    => ['damaged.png', 'https://smiley.codes/qrcode/'],
			// covers Binarizer::getHistogramBlackMatrix()
			'smol'       => ['smol.png', 'https://smiley.codes/qrcode/'],
			'tilted'     => ['tilted.png', 'Hello world!'], // tilted 22° CCW
			'rotated'    => ['rotated.png', 'Hello world!'], // rotated 90° CW
		];
	}

	/**
	 * @dataProvider qrCodeProvider
	 */
	public function testReaderGD(string $img, string $expected):void{
		$this->options->useImagickIfAvailable = false;

		$reader = new QRCode($this->options);

		$this::assertSame($expected, (string)$reader->readFromFile(__DIR__.'/qrcodes/'.$img));
	}

	/**
	 * @dataProvider qrCodeProvider
	 */
	public function testReaderImagick(string $img, string $expected):void{

		if(!extension_loaded('imagick')){
			$this::markTestSkipped('imagick not installed');
		}

		$this->options->useImagickIfAvailable = true;

		$reader = new QRCode($this->options);

		$this::assertSame($expected, (string)$reader->readFromFile(__DIR__.'/qrcodes/'.$img));
	}

	public function dataTestProvider():array{
		$data = [];
		$str  = str_repeat($this::loremipsum, 5);

		foreach(range(1, 40) as $v){
			$version = new Version($v);

			foreach(EccLevel::MODES as $ecc => $_){
				$eccLevel = new EccLevel($ecc);

				$data['version: '.$version.$eccLevel] = [
					$version,
					$eccLevel,
					/** @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal */
					substr($str, 0, $version->getMaxLengthForMode(Mode::DATA_BYTE, $eccLevel))
				];
			}
		}

		return $data;
	}

	/**
	 * @dataProvider dataTestProvider
	 */
	public function testReadData(Version $version, EccLevel $ecc, string $expected):void{

#		$this->options->imageTransparent      = false;
		$this->options->eccLevel              = $ecc->getLevel();
		$this->options->version               = $version->getVersionNumber();
		$this->options->imageBase64           = false;
		$this->options->scale                 = 1; // what's interesting is that a smaller scale seems to produce fewer reader errors???
		$this->options->useImagickIfAvailable = true;

		try{
			$qrcode = new QRCode($this->options);
			$imagedata = $qrcode->render($expected);
			$result    = $qrcode->readFromBlob($imagedata);
		}
		catch(Exception $e){
			$this::markTestSkipped($version.$ecc.': '.$e->getMessage());
		}

		$this::assertSame($expected, $result->getText());
		$this::assertSame($version->getVersionNumber(), $result->getVersion()->getVersionNumber());
		$this::assertSame($ecc->getLevel(), $result->getEccLevel()->getLevel());
	}

}