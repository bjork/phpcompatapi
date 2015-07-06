/* global React */

(function () {
	/**
	 * CompatibilityUploader encapsulates an <input type=file>.
	 */
	var CompatibilityUploader = React.createClass({displayName: "CompatibilityUploader",
		/**
		 * Get file from input and pass it forward.
		 */
		handleUpload: function () {
			var fileInput = this.refs.file.getDOMNode();
			if ( fileInput.files.length > 0 ) {
				var files = fileInput.files;
				this.props.onFileUpload({ files: files });
			}
		},
		render: function () {
			return (
				React.createElement("div", {className: "uploader"}, 
					React.createElement("form", {className: "uploaderForm", ref: "form", encType: "multipart/form-data"}, 
						React.createElement("input", {type: "file", accept: "text/php", ref: "file", onChange: this.handleUpload, multiple: true})
					)
				)
			);
		}
	});

	var CompatibilityFileIssues = React.createClass({displayName: "CompatibilityFileIssues",

		/**
		 * Transforms issues from the API to a more human readable form.
		 */
		transformIssues: function ( info ) {
			var issues = [];

			for ( var type in info ) {
				for ( var property in info[ type ] ) {
					var details = info[ type ][ property ];

					// Make the type singular.
					var type_singular = type.substr( 0, type.length - 1 );
					// Handle a special case.
					if ( 'classes' === type ) {
						type_singular = 'class';
					}

					// Make it begin with capital first letter:
					type_singular = type_singular.substr( 0, 1 ).toUpperCase() +type_singular.substr( 1 );

					// Create a human readable version requirement string
					var version = '';
					if ( details.php_min && details.php_max ) {
						version = details.php_min + '–' + details.php_max;
					} else if ( details.php_min ) {
						version = '>= ' + details.php_min;
					} else if ( details.php_max ) {
						version = '<= ' + details.php_max;
					}

					issues.push({ type: type_singular, name: property, phpVersion: version });
				}
			}

			return issues;
		},

		render: function () {
			this.props.issues = this.transformIssues( this.props.issues );

			// Create sub nodes for file issues
			var issueNodes = this.props.issues.map(function (issue) {

				var id = issue.name;
				return (
					React.createElement(CompatibilityIssue, {type: issue.type, name: issue.name, phpVersion: issue.phpVersion})
				);
			});

			return (
				React.createElement("div", null, 
				React.createElement("h3", null, this.props.fileName), 
				React.createElement("ol", null, issueNodes)
				)
			);
		}
	});

	/**
	 * CompatibilityIssue is an li element.
	 */
	var CompatibilityIssue = React.createClass({displayName: "CompatibilityIssue",
		render: function () {
			return (
				React.createElement("li", null, React.createElement("span", {className: "type"}, this.props.type), " ", React.createElement("code", {className: "name"}, this.props.name), " requires PHP ", React.createElement("span", {className: "phpVersion"}, this.props.phpVersion), ".")
			);
		}
	});

	var CompatibilityResults = React.createClass({displayName: "CompatibilityResults",
		render: function () {
			// Do not render if the validity is not determined yet.
			if ( null === this.props.valid ) {
				return null;
			}

			// Create sub nodes for file issues
			var issueFileNodes = this.props.fileIssues.map(function (issueFile) {
				var id = issueFile.name;
				return (
					React.createElement(CompatibilityFileIssues, {fileName: issueFile.file, issues: issueFile.issues})
				);
			});

			return (
				React.createElement("div", {className: "results"}, 
					React.createElement("p", null, "The validation ", React.createElement("span", {className: "failed", style: {display: false === this.props.valid ? 'inline' : 'none'}}, "failed"), React.createElement("span", {className: "passed", style: {display: true === this.props.valid ? 'inline' : 'none'}}, "passed"), ". ", this.props.error), 

					React.createElement("div", {className: "issues", style: {display: this.props.fileIssues.length > 0 ? 'block' : 'none'}}, 
						React.createElement("h2", null, "Issues found"), 
						issueFileNodes
					)
				)
			);
		}
	});

	/**
	 * The main component.
	 */
	var CompatibilityTester = React.createClass({displayName: "CompatibilityTester",
		getInitialState: function () {
			return { valid: null, fileIssues: [], error: null, loading: false };
		},

		/**
		 * Handles Ajax response.
		 */
		onReadyStateChangeHandler: function ( event ) {
			var status, text, readyState;

			try {
				readyState = event.target.readyState;
				text       = event.target.responseText;
				status     = event.target.status;
			} catch( e ) {
				return;
			}

			// Test if the request is finished (4) and succeeded.
			if ( 4 === readyState && 200 === status && text ) {
				var response = '';
				try {
					response = JSON.parse( text );
				} catch ( err ) {
					// The response was malformed. Create it manually.
					response = { passes: false, info: [], error: text };
				}

				var fileIssues = [];
				if ( false === response.passes ) {
					fileIssues = response.info;
				}

				// Update state.
				this.setState({ valid: response.passes, fileIssues: fileIssues, error: null, loading: false });

			// Test if the request is finished (4) but there was an error.
			} else if ( 4 === readyState && text ) {
				try {
					var response = JSON.parse( text );
				} catch ( err ) {
					// The response was malformed. Create it manually.
					response = { passes: false, info: [], error: text };
				}
				// Update state.
				this.setState({ valid: false, fileIssues: [], error: response.error, loading: false });
			}
		},

		/**
		 * Upload a file through XMLHttpRequest
		 */
		uploadFiles: function ( files ) {
			this.setState({ loading: true });

			var formData = new FormData();

			// Set each selected file to formData as an array
			for ( var i = 0; i < files.files.length; i++ ) {
				formData.append( 'file[]', files.files[ i ] );
			}

			var xhr = new XMLHttpRequest();
			xhr.addEventListener( 'readystatechange', this.onReadyStateChangeHandler, false );
			xhr.open( 'POST', wct_api_root + 'api/v1/test/', true );
			xhr.send( formData);
		},

		render: function() {

			var loading = this.state.loading ? React.createElement("p", {className: "loading"}, React.createElement("img", {src: "assets/img/loading.gif", alt: "Processing…"})) : '';

			return (
				React.createElement("div", {className: "tester"}, 
					React.createElement("h1", null, "Platform PHP Compatibility Tester"), 
					React.createElement(CompatibilityUploader, {onFileUpload: this.uploadFiles}), 
					 loading, 
					React.createElement(CompatibilityResults, {valid: this.state.valid, fileIssues: this.state.fileIssues, error: this.state.error})
				)
			);
		}
	});

	React.render(
		React.createElement(CompatibilityTester, null),
		document.getElementById( 'compatibility-tester' )
	);

})();