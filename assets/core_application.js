$(document).ready(function() {
  $('.changeApplication').click(function() {
    var urlSso = $(this).data('sso-method');
    var redirectionRoute = $(this).data('redirection-route');
    $.ajax({
      url: $(this).data('url'),
      type: "GET",
      success: function (data) {
        window.location.replace(urlSso +"?login="+data.login+"&token="+data.token+"&route="+redirectionRoute);
      },
      error: function (data) {
        alert(data.responseJSON.error_message);
      }
    })
  })

  $("a.link-with-loader").click(function(){
    $('<div class="global-loader"><h4><i class="fa fa-spin fa-spinner fa-fw"></i>Chargement en cours ...</h4></div>').prependTo(document.body);
  })

  $("div.link-with-loader").click(function(){
    $('<div class="global-loader"><h4><i class="fa fa-spin fa-spinner fa-fw"></i>Redirection en cours ...</h4></div>').prependTo(document.body);
  })

  $('.dropdown-toggle').click(function(e) {
    $(e).parent().toggleClass('show');
    $(e).parent().children('.dropdown-menu').toggleClass('show');
  })

  $(".left-menu-sm").click(function(){
    $(".left-menu").toggleClass('visible');
    $(".app-container").toggleClass('small-cn');
  });

  $("#close-menu").click(function(){
    $(".left-menu").toggleClass('visible');
    $(".app-container").toggleClass('small-cn');
  });
})
