/* global React */

(function () {
	var CompatibilityUploader = React.createClass({displayName: "CompatibilityUploader",
		handleUpload: function () {
			var fileInput = this.refs.file.getDOMNode();
			if ( fileInput.files.length > 0 ) {
				var file = fileInput.files[0];
				this.props.onFileUpload({ file: file });
			}
		},
		render: function () {
			return (
				React.createElement("div", {className: "uploader"}, 
					React.createElement("form", {className: "uploaderForm", ref: "form", encType: "multipart/form-data"}, 
						React.createElement("input", {type: "file", accept: "text/php", ref: "file", onChange: this.handleUpload})
					)
				)
			);
		}
	});

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

			var issueNodes = this.props.issues.map(function (issue) {
				var id = issue.type + issue.name;
				return (
					React.createElement(CompatibilityIssue, {key: id, type: issue.type, name: issue.name, phpVersion: issue.phpVersion})
				);
			});

			return (
				React.createElement("div", {className: "results"}, 
					React.createElement("p", null, "The validation of the file ", React.createElement("span", {className: "failed", style: {display: false === this.props.valid ? 'inline' : 'none'}}, "failed"), React.createElement("span", {className: "passed", style: {display: true === this.props.valid ? 'inline' : 'none'}}, "passed"), ". ", this.props.error), 

					React.createElement("div", {className: "issues", style: {display: this.props.issues.length > 0 ? 'block' : 'none'}}, 
						React.createElement("h2", null, "Issues found"), 
						React.createElement("ol", null, issueNodes)
					)
				)
			);
		}
	});

	var CompatibilityTester = React.createClass({displayName: "CompatibilityTester",
		getInitialState: function () {
			return { valid: null, issues: [], error: null };
		},

		onReadyStateChangeHandler: function ( event ) {
			var status, text, readyState;
			try {
				readyState = event.target.readyState;
				text = event.target.responseText;
				status = event.target.status;
			}
			catch(e) {
				return;
			}

			if ( 4 === readyState && 200 === status && text ) {
				// todo try catch for SyntaxError exception
				var response = JSON.parse( text );

				var issues = [];
				if ( false === response.passes ) {
					issues = this.transformIssues( response.info );
				}

				this.setState({ valid: response.passes, issues: issues, error: null });
			} else if ( 4 === readyState && text ) {
				// todo try catch for SyntaxError exception
				var response = JSON.parse( text );
				this.setState({ valid: false, error: response.error });
			}
		},

		transformIssues: function ( info ) {
			var issues = [];

			for ( var type in info ) {
				for ( var property in info[ type ] ) {
					var details = info[ type ][ property ];

					// Make the type singular
					var type_singular = type.substr( 0, type.length - 1 );
					if ( 'e' === type_singular.substr( type_singular.length - 1 ) ) {
						// Handle 'es' plural in 'class'
						type_singular = type_singular.substr( 0, type_singular.length - 1 );
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

		uploadFile: function ( file ) {
			var formData = new FormData();
			formData.append('file', file.file);

			var xhr = new XMLHttpRequest();
			xhr.addEventListener( 'readystatechange', this.onReadyStateChangeHandler, false );
			xhr.open( 'POST', wct_api_root + 'api/v1/test/', true );
			xhr.send( formData);
		},

		render: function() {
			return (
				React.createElement("div", {className: "tester"}, 
					React.createElement("h1", null, "Platform PHP Compatibility Tester"), 
					React.createElement(CompatibilityUploader, {onFileUpload: this.uploadFile}), 
					React.createElement(CompatibilityResults, {valid: this.state.valid, issues: this.state.issues, error: this.state.error})
				)
			);
		}
	});

	React.render(
		React.createElement(CompatibilityTester, null),
		document.getElementById( 'compatibility-tester' )
	);

})();