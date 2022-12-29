const { test, expect } = require( '@playwright/test' );
import config from 'config';
const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = require( '../../utils/payments' );
