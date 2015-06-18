/* global React */

(function () {
	var CompatibilityUploader = React.createClass({
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
						<input type="file" ref="file" onChange={this.handleUpload}/>
					</form>
				</div>
			);
		}
	});

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

			var issueNodes = this.props.issues.map(function (issue) {
				var id = issue.type + issue.name;
				return (
					<CompatibilityIssue key={id} type={issue.type} name={issue.name} phpVersion={issue.phpVersion} />
				);
			});

			return (
				<div className="results">
					<p>The validation of the file <span className="failed" style={{display: false === this.props.valid ? 'inline' : 'none'}}>failed</span><span className="passed" style={{display: true === this.props.valid ? 'inline' : 'none'}}>passed</span>.</p>

					<div className="issues" style={{display: this.props.issues.length > 0 ? 'block' : 'none'}}>
						<p>Issues found:</p>
						<ol>{issueNodes}</ol>
					</div>
				</div>
			);
		}
	});

	var CompatibilityTester = React.createClass({
		getInitialState: function () {
			return { valid: null, issues: [] };
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

				this.setState({ valid: response.passes, issues: issues });
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
				<div className="tester">
					<h1>Platform PHP Compatibility Tester</h1>
					<CompatibilityUploader onFileUpload={this.uploadFile} />
					<CompatibilityResults valid={this.state.valid} issues={this.state.issues} />
				</div>
			);
		}
	});

	React.render(
		<CompatibilityTester/>,
		document.getElementById( 'compatibility-tester' )
	);

})();