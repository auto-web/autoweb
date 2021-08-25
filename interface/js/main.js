var autoweb = {

  jobs: [],
  is_admin: false,

  main: function() {
    if (autoweb.is_admin) {
      autoweb.refreshJobs();
    }
  },

  refreshJobs: function() {
    jQuery.getJSON('/jobs.php', function(jobs) {
      if (jobs !== undefined && jobs !== null && jobs.length > 0) {
        autoweb.jobs = jobs;
        jQuery('#jobs-tooltip').html('<ul>' + autoweb.jobs.reduce(function(result, item) {
          return result + '<li>' + (item.status == 'running' ? '<div class="spinner"><div class="double-bounce1"></div><div class="double-bounce2"></div></div>' : '') + '<span>' + item.date + ' ' + item.operation + '</span></li>';
        }, '') + '</ul>');
        jQuery('#jobs-notify').addClass('has-jobs').find('.content').html('<a href="#" class="btn btn-info m-2 rounded">' + jobs.length + ' ' + (jobs.length <= 1 ? 'tâche' : 'tâches') + ' en attente</a>');
        jQuery('#jobs-notify a').click(autoweb.onClickJobsNotification);
        setTimeout(autoweb.refreshJobs, 10000);
      } else {
        autoweb.jobs = [];
        jQuery('#jobs-notify').removeClass('has-jobs').html('');
      }
    });
  },

  onClickJobsNotification: function(e) {
    e.preventDefault();
  }

};

jQuery(document).ready(autoweb.main);
