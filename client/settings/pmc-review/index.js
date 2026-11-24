import React, { useEffect, useMemo, useState } from 'react';
import { getMethodLabel } from './method-label';
import StepReview from './step-review';
import StepConfirm from './step-confirm';
import './style.scss';

export { default as usePmcReview } from './use-pmc-review';

const PmcReviewTable = ( {
	review,
	error,
	isBusy,
	refresh,
	save,
	hasUnsavedChanges = false,
} ) => {
	const [ selection, setSelection ] = useState( {} );
	const [ step, setStep ] = useState( 'review' );

	// Every refetch can carry a different comparison, so picks restart from the server suggestion instead of mixing old and new rows.
	useEffect( () => {
		const initial = {};
		( review?.methods || [] ).forEach( ( method ) => {
			initial[ method.id ] = method.preselected;
		} );
		setSelection( initial );
		setStep( 'review' );
	}, [ review?.revision, review?.methods ] );

	const rows = useMemo(
		() =>
			[ ...( review?.methods || [] ) ]
				.map( ( method ) => ( {
					...method,
					label: getMethodLabel( method.id ),
					differs: method.local !== method.stripe,
				} ) )
				.sort(
					( a, b ) =>
						Number( b.differs ) - Number( a.differs ) ||
						a.label.localeCompare( b.label )
				),
		[ review?.methods ]
	);

	const isOn = ( row ) => Boolean( selection[ row.id ] );
	const idsWhere = ( predicate ) =>
		rows.filter( predicate ).map( ( row ) => row.id );

	const onToggle = ( id, checked ) =>
		setSelection( { ...selection, [ id ]: checked } );

	const onRefresh = () => {
		setStep( 'review' );
		refresh();
	};

	if ( step === 'review' ) {
		return (
			<StepReview
				review={ review }
				rows={ rows }
				isOn={ isOn }
				onToggle={ onToggle }
				onContinue={ () => setStep( 'confirm' ) }
			/>
		);
	}

	return (
		<StepConfirm
			stripeChanges={ {
				enabled: idsWhere( ( row ) => isOn( row ) && ! row.stripe ),
				disabled: idsWhere( ( row ) => ! isOn( row ) && row.stripe ),
			} }
			wooChanges={ {
				enabled: idsWhere( ( row ) => isOn( row ) && ! row.local ),
				disabled: idsWhere( ( row ) => ! isOn( row ) && row.local ),
			} }
			error={ error }
			isBusy={ isBusy }
			hasUnsavedChanges={ hasUnsavedChanges }
			onBack={ () => setStep( 'review' ) }
			onSave={ () => save( idsWhere( isOn ) ) }
			onRefresh={ onRefresh }
		/>
	);
};

export default PmcReviewTable;
