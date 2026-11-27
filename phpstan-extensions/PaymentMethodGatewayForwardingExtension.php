<?php

namespace WooCommerce\Stripe\PHPStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Teaches PHPStan about WC_Stripe_UPE_Payment_Method::__call(), which forwards unknown
 * method calls to the main WC_Stripe_UPE_Payment_Gateway instance.
 */
final class PaymentMethodGatewayForwardingExtension implements MethodsClassReflectionExtension {
	private const PAYMENT_METHOD_CLASS = 'WC_Stripe_UPE_Payment_Method';
	private const GATEWAY_CLASS        = 'WC_Stripe_UPE_Payment_Gateway';

	/**
	 * @var ReflectionProvider
	 */
	private $reflection_provider;

	public function __construct( ReflectionProvider $reflection_provider ) {
		$this->reflection_provider = $reflection_provider;
	}

	public function hasMethod( ClassReflection $class_reflection, string $method_name ): bool {
		return null !== $this->find_gateway_method( $class_reflection, $method_name );
	}

	public function getMethod( ClassReflection $class_reflection, string $method_name ): MethodReflection {
		$method = $this->find_gateway_method( $class_reflection, $method_name );
		if ( null === $method ) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		return $method;
	}

	private function find_gateway_method( ClassReflection $class_reflection, string $method_name ): ?MethodReflection {
		if ( ! $class_reflection->is( self::PAYMENT_METHOD_CLASS ) ) {
			return null;
		}

		if ( ! $this->reflection_provider->hasClass( self::GATEWAY_CLASS ) ) {
			return null;
		}

		$gateway = $this->reflection_provider->getClass( self::GATEWAY_CLASS );
		if ( ! $gateway->hasNativeMethod( $method_name ) ) {
			return null;
		}

		$method = $gateway->getNativeMethod( $method_name );
		// Static methods are excluded because there is no __callStatic(): a static call would
		// fatal at runtime. Instance calls to them do work through __call(), so this is
		// deliberately stricter than the runtime.
		return $method->isPublic() && ! $method->isStatic() ? $method : null;
	}
}
