/**
 * Adds WooCommerce Bookings products to the cart via the Store API
 * `cart/add-item` route (Bookings 3.0.0+ `WC_Bookings_Cart_Store_API`, which
 * reads a `booking_configuration` param). Persons and customer-defined
 * durations have no representation there and fall back to the legacy endpoint.
 */

const pad = ( value ) => String( value ).padStart( 2, '0' );

/**
 * Convert Bookings' ISO-8601 slot value (e.g. `2026-07-01T09:00:00+0000`) to the
 * `Y-m-d H:i:s` form the Store API expects, dropping the offset (the server
 * re-applies the booking timezone, sent separately as `local_timezone`).
 *
 * @param {string} iso The ISO-8601 slot value.
 * @return {string|null} A `Y-m-d H:i:s` string, or null if it doesn't match.
 */
const isoSlotToStoreApiDate = ( iso ) => {
	const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/.exec( iso ?? '' );
	return match ? `${ match[ 1 ] } ${ match[ 2 ] }` : null;
};

/**
 * Build the Store API `booking_configuration` from the Bookings product-page
 * form. Returns null — caller falls back to legacy — when the booking can't be
 * represented (persons / customer-defined duration, or nothing selected yet).
 *
 * @param {HTMLElement|null} form The `.wc-bookings-booking-form` element.
 * @return {Object|null} `{ date, local_timezone, resource_id? }` or null.
 */
export const buildBookingConfiguration = ( form ) => {
	if ( ! form ) {
		return null;
	}

	const fieldValue = ( name ) =>
		form.querySelector( `[name="${ name }"]` )?.value?.trim() ?? '';

	// Persons / customer-defined-duration bookings can't be represented; legacy.
	if (
		form.querySelector( '[name^="wc_bookings_field_persons"]' ) ||
		form.querySelector( '[name$="_to_year"]' )
	) {
		return null;
	}

	let date = null;
	const yearMonth = fieldValue( 'wc_bookings_field_start_date_yearmonth' );

	if ( yearMonth ) {
		// Month booking: stored as `Y-n`, booked from the first of the month.
		const [ year, month ] = yearMonth.split( '-' );
		if ( year && month ) {
			date = `${ year }-${ pad( month ) }-01 00:00:00`;
		}
	} else if (
		form.querySelector( '[name="wc_bookings_field_start_date_time"]' )
	) {
		// Datetime booking: the chosen slot carries the full ISO-8601 datetime.
		date = isoSlotToStoreApiDate(
			fieldValue( 'wc_bookings_field_start_date_time' )
		);
	} else {
		// Date/day booking: assemble from the year/month/day inputs.
		const year = fieldValue( 'wc_bookings_field_start_date_year' );
		const month = fieldValue( 'wc_bookings_field_start_date_month' );
		const day = fieldValue( 'wc_bookings_field_start_date_day' );
		if ( year && month && day ) {
			date = `${ year }-${ pad( month ) }-${ pad( day ) } 00:00:00`;
		}
	}

	if ( ! date ) {
		return null;
	}

	const config = {
		date,
		local_timezone:
			fieldValue( 'wc_bookings_field_start_date_local_timezone' ) ||
			Intl.DateTimeFormat().resolvedOptions().timeZone,
	};

	const resourceId = fieldValue( 'wc_bookings_field_resource' );
	if ( resourceId && resourceId !== '0' ) {
		config.resource_id = Number( resourceId );
	}

	return config;
};
