(function() {

	tinymce.create('tinymce.plugins.rally_pledge_button', {

		init : function(ed, url){
			ed.addButton('rally_pledge_button', {
				title : 'Add Rally.org Pledge Widget',
        cmd : "insertrally",
				image: sc_img
			});

      ed.addCommand("insertrally", function() {
        var rallyPage = prompt("What is the address of the rally?", "http://rally.org/buzkashiboys");
        if (rallyPage !== null) {
          shortcode = "[rally-pledge page=\""+rallyPage+"\"]";
          ed.execCommand('mceInsertContent', 0, shortcode);
        }
      });
		},

		getInfo : function() {
			return {
				longname : 'Rally pledge button plugin',
				author : 'Tommy Devol & Jinjutha Hancock',
				authorurl : 'http://rally.org/corp/careers',
				infourl : '',
				version : "1.0"
			};
		}

	});

	tinymce.PluginManager.add('rally_pledge_button', tinymce.plugins.rally_pledge_button);
	
})();	
