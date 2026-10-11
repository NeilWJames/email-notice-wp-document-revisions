/**
 * Jest transform for the src/js browser scripts.
 *
 * In the browser each script's top-level functions are globals (called from onclick attributes and
 * from each other). Loaded with require() they would be private to the module, so this appends a
 * line that copies each top-level function onto the global object. Nothing else changes, so line
 * numbers (and coverage) match the source.
 */
module.exports = {
	process( source ) {
		const names = [ ...source.matchAll( /^function\s+([A-Za-z_$][\w$]*)\s*\(/gm ) ].map( ( match ) => match[ 1 ] );
		const exports = names.map( ( name ) => `globalThis.${ name } = ${ name };` ).join( ' ' );
		return { code: source + '\n' + exports + '\n' };
	},
};
