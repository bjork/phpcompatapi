/* global React */

(function () {
	/**
	 * CompatibilityUploader encapsulates an <input type=file>.
	 */
	var CompatibilityUploader = React.createClass({
		/**
		 * Get file from input and pass it forward.
		 */
		handleUpload: function () {
			var fileInput = this.refs.file.getDOMNode();
			if ( fileInput.files.length > 0 ) {
				var file = fileInput.files[0];
				this.props.onFileUpload({ file: file });
			}
		},
		render: function () {
			return (
				<div className="uploader">
					<form className="uploaderForm" ref="form" encType="multipart/form-data">
						<input type="file" accept="text/php" ref="file" onChange={this.handleUpload}/>
					</form>
				</div>
			);
		}
	});

	/**
	 * CompatibilityIssue is an li element.
	 */
	var CompatibilityIssue = React.createClass({
		render: function () {
			return (
				<li><span className="type">{this.props.type}</span> <code className="name">{this.props.name}</code> requires PHP <span className="phpVersion">{this.props.phpVersion}</span>.</li>
			);
		}
	});

	var CompatibilityResults = React.createClass({
		render: function () {
			// Do not render if the validity is not determined yet.
			if ( null === this.props.valid ) {
				return null;
			}

			// Create sub nodes for issues
			var issueNodes = this.props.issues.map(function (issue) {
				var id = issue.type + issue.name;
				return (
					<CompatibilityIssue key={id} type={issue.type} name={issue.name} phpVersion={issue.phpVersion} />
				);
			});

			return (
				<div className="results">
					<p>The validation of the file <span className="failed" style={{display: false === this.props.valid ? 'inline' : 'none'}}>failed</span><span className="passed" style={{display: true === this.props.valid ? 'inline' : 'none'}}>passed</span>. {this.props.error}</p>

					<div className="issues" style={{display: this.props.issues.length > 0 ? 'block' : 'none'}}>
						<h2>Issues found</h2>
						<ol>{issueNodes}</ol>
					</div>
				</div>
			);
		}
	});

	/**
	 * The main component.
	 */
	var CompatibilityTester = React.createClass({
		getInitialState: function () {
			return { valid: null, issues: [], error: null };
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

				var issues = [];
				if ( false === response.passes ) {
					issues = this.transformIssues( response.info );
				}

				// Update state.
				this.setState({ valid: response.passes, issues: issues, error: null });

			// Test if the request is finished (4) but there was an error.
			} else if ( 4 === readyState && text ) {
				try {
					var response = JSON.parse( text );
				} catch ( err ) {
					// The response was malformed. Create it manually.
					response = { passes: false, info: [], error: text };
				}
				// Update state.
				this.setState({ valid: false, issues: response.info, error: response.error });
			}
		},

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

		/**
		 * Upload a file through XMLHttpRequest
		 */
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
				<div className="tester">
					<h1>Platform PHP Compatibility&nbsp;Tester</h1>
					<CompatibilityUploader onFileUpload={this.uploadFile} />
					<CompatibilityResults valid={this.state.valid} issues={this.state.issues} error={this.state.error} />
				</div>
			);
		}
	});

	React.render(
		<CompatibilityTester/>,
		document.getElementById( 'compatibility-tester' )
	);

})();