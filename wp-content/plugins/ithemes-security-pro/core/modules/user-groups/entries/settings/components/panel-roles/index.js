/**
 * External dependencies
 */
import memize from 'memize';
import { without, some } from 'lodash';

/**
 * WordPress dependencies
 */
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { HierarchicalCheckboxControl } from '@ithemes/security-components';
import { bifurcate } from '@ithemes/security-utils';

const toCanonicalGroup = memize( ( availableRoles ) => {
	const group = [
		{
			value: '$administrator$',
			label: __( 'Administrator Capabilities', 'it-l10n-ithemes-security-pro' ),
		},
		{
			value: '$editor$',
			label: __( 'Editor Capabilities', 'it-l10n-ithemes-security-pro' ),
		},
		{
			value: '$author$',
			label: __( 'Author Capabilities', 'it-l10n-ithemes-security-pro' ),
		},
		{
			value: '$contributor$',
			label: __( 'Contributor Capabilities', 'it-l10n-ithemes-security-pro' ),
		},
		{
			value: '$subscriber$',
			label: __( 'Subscriber Capabilities', 'it-l10n-ithemes-security-pro' ),
		},
	];

	if ( some( availableRoles, ( role ) => role.canonical === '' ) ) {
		group.push( {
			value: '$other$',
			label: __( 'Other', 'it-l10n-ithemes-security-pro' ),
			selectable: false,
		} );
	}

	for ( const role in availableRoles ) {
		if ( ! availableRoles.hasOwnProperty( role ) ) {
			continue;
		}

		const { canonical, label } = availableRoles[ role ];

		group.push( {
			value: role,
			parent: canonical.length > 0 ? `$${ canonical }$` : '$other$',
			label,
		} );
	}

	return Object.values( group );
} );

function PanelRoles( { canonical, roles, onChange, available, disabled = false } ) {
	const value = [
		...roles,
		...canonical.map( ( role ) => `$${ role }$` ),
	];

	return (
		<HierarchicalCheckboxControl
			label={ __( 'Select Roles', 'it-l10n-ithemes-security-pro' ) }
			help={ __( 'Add users with the selected roles to this group.', 'it-l10n-ithemes-security-pro' ) }
			value={ value }
			disabled={ disabled }
			options={ toCanonicalGroup( available ) }
			onChange={ ( change ) => {
				const [ newCanonical, newRoles ] = bifurcate( change, ( role ) => role.startsWith( '$' ) && role.endsWith( '$' ) );

				onChange( {
					roles: newRoles,
					canonical: without( newCanonical.map( ( role ) => role.slice( 1, -1 ) ), 'other' ),
				} );
			} }
		/>
	);
}

export default compose( [
	withSelect( ( select, { groupId } ) => ( {
		roles: select( 'ithemes-security/user-groups-editor' ).getEditedGroupAttribute( groupId, 'roles' ) || [],
		canonical: select( 'ithemes-security/user-groups-editor' ).getEditedGroupAttribute( groupId, 'canonical' ) || [],
		available: select( 'ithemes-security/core' ).getRoles(),
	} ) ),
	withDispatch( ( dispatch, { groupId } ) => ( {
		onChange( edit ) {
			return dispatch( 'ithemes-security/user-groups-editor' ).editGroup( groupId, edit );
		},
	} ) ),
] )( PanelRoles );
