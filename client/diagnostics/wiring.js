import { getRecorder } from 'wcstripe/diagnostics/recorder';

function isActive() {
	return !! window.wcStripeDiag?.active;
}

export function diagAttachExpress( eceButton ) {
	if ( ! isActive() ) {
		return;
	}
	getRecorder().attachExpress( eceButton );
}

export function diagAttach( element, kind, surface ) {
	if ( ! isActive() ) {
		return;
	}
	getRecorder().attach( element, kind, surface );
}

export function diagBlocksPaymentSetupStart( site ) {
	if ( ! isActive() ) {
		return null;
	}
	return getRecorder().recordBlocksPaymentSetupStart( site );
}

export function diagBlocksPaymentSetupEnd( handle, result ) {
	if ( ! handle || ! isActive() ) {
		return;
	}
	getRecorder().recordBlocksPaymentSetupEnd( handle, result );
}
