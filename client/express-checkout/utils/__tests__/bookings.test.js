import { buildBookingConfiguration } from '../bookings';

/**
 * Build a `.wc-bookings-booking-form` element from a list of
 * `[name, value]` field pairs.
 *
 * @param {Array<[string, string]>} fields Field name/value pairs.
 * @return {HTMLElement} The form element.
 */
const makeForm = ( fields ) => {
	const form = document.createElement( 'div' );
	form.className = 'wc-bookings-booking-form';
	fields.forEach( ( [ name, value ] ) => {
		const input = document.createElement( 'input' );
		input.setAttribute( 'name', name );
		input.value = value;
		form.appendChild( input );
	} );
	return form;
};

describe( 'buildBookingConfiguration', () => {
	const TZ = Intl.DateTimeFormat().resolvedOptions().timeZone;

	test( 'returns null when no form is provided', () => {
		expect( buildBookingConfiguration( null ) ).toBeNull();
	} );

	test( 'builds a date/day booking from year/month/day, zero-padding', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
		] );

		expect( buildBookingConfiguration( form ) ).toEqual( {
			date: '2026-07-01 00:00:00',
			local_timezone: TZ,
		} );
	} );

	test( 'converts a datetime slot ISO-8601 value to Y-m-d H:i:s (dropping the offset)', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '07' ],
			[ 'wc_bookings_field_start_date_day', '01' ],
			[ 'wc_bookings_field_start_date_time', '2026-07-01T09:00:00+0000' ],
			[ 'wc_bookings_field_start_date_local_timezone', 'Europe/Lisbon' ],
		] );

		expect( buildBookingConfiguration( form ) ).toEqual( {
			date: '2026-07-01 09:00:00',
			local_timezone: 'Europe/Lisbon',
		} );
	} );

	test( 'builds a month booking from the Y-n yearmonth field', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_yearmonth', '2026-7' ],
		] );

		expect( buildBookingConfiguration( form ) ).toEqual( {
			date: '2026-07-01 00:00:00',
			local_timezone: TZ,
		} );
	} );

	test( 'includes a selected resource id as a number', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_resource', '42' ],
		] );

		expect( buildBookingConfiguration( form ).resource_id ).toBe( 42 );
	} );

	test( 'ignores the placeholder resource value of 0', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_resource', '0' ],
		] );

		expect( buildBookingConfiguration( form ) ).not.toHaveProperty(
			'resource_id'
		);
	} );

	test( 'returns null for persons-based bookings (not expressible over Store API)', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_persons', '2' ],
		] );

		expect( buildBookingConfiguration( form ) ).toBeNull();
	} );

	test( 'returns null for customer-defined-duration (date range) bookings', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_start_date_to_year', '2026' ],
			[ 'wc_bookings_field_duration', '3' ],
		] );

		expect( buildBookingConfiguration( form ) ).toBeNull();
	} );

	test( 'returns null for customer-defined-duration (nights) bookings without a range picker', () => {
		// "N nights" bookings render `wc_bookings_field_duration` but no
		// `_to_year`, so the duration field is what flags the legacy fallback.
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_duration', '2' ],
		] );

		expect( buildBookingConfiguration( form ) ).toBeNull();
	} );

	test( 'returns null when no date has been selected yet', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '' ],
			[ 'wc_bookings_field_start_date_day', '' ],
		] );

		expect( buildBookingConfiguration( form ) ).toBeNull();
	} );

	test( 'returns null for a datetime booking with no slot selected', () => {
		const form = makeForm( [
			[ 'wc_bookings_field_start_date_year', '2026' ],
			[ 'wc_bookings_field_start_date_month', '7' ],
			[ 'wc_bookings_field_start_date_day', '1' ],
			[ 'wc_bookings_field_start_date_time', '' ],
		] );

		expect( buildBookingConfiguration( form ) ).toBeNull();
	} );
} );
