<?php

class TestDeprecatedClass {

	use DeprecationHelper;

	protected $protectedDeprecated = 1;
	protected $protectedNonDeprecated = 1;
	private $privateDeprecated = 1;
	private $privateNonDeprecated = 1;
	private $fallbackDeprecated = 1;

	public function __construct() {
		$this->deprecatePublicProperty( 'protectedDeprecated', '1.23' );
		$this->deprecatePublicProperty( 'privateDeprecated', '1.24' );

		$this->deprecatePublicPropertyFallback( 'fallbackDeprecated', '1.25',
			function () {
				return $this->fallbackDeprecated;
			},
			function ( $value ) {
				$this->fallbackDeprecated = $value;
			}
		);
		$this->deprecatePublicPropertyFallback( 'fallbackGetterOnly', '1.25',
			function () {
				return 1;
			}
		);
	}

	public function setThings( $prod, $prond, $prid, $prind ) {
		$this->protectedDeprecated = $prod;
		$this->protectedNonDeprecated = $prond;
		$this->privateDeprecated = $prid;
		$this->privateNonDeprecated = $prind;
	}

	public function getThings() {
		return [
			'prod' => $this->protectedDeprecated,
			'prond' => $this->protectedNonDeprecated,
			'prid' => $this->privateDeprecated,
			'prind' => $this->privateNonDeprecated,
		];
	}

}
