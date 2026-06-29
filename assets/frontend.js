jQuery(function($){
  function esc(s){ return $('<div>').text(s || '').html(); }

  function setApplicationMode(mode, voter){
    if(mode === 'update'){
      $('#ghv-application-type').val('update');
      $('#ghv-voter-id').val(voter && voter.id ? voter.id : 0);
      $('#ghv-application-title').text('Request Correction / Update Existing Details');
      $('#ghv-application-intro').text('Your record was found. Please submit the corrected details and upload Aadhaar proof. Membership fee is not required for a correction request.');
      $('#ghv-bank-section,.ghv-payment-field').hide();
      $('#ghv-transaction-id,#ghv-payment-receipt').prop('required', false).val('');
      $('#ghv-update-box').show();
      $('#ghv-requested-update').prop('required', true);
      if(voter){
        $('#ghv-full-name').val(voter.first_owner || voter.second_owner || '');
        $('#ghv-tower').val(voter.tower || '');
        $('#ghv-flat').val(voter.flat || '');
      }
    } else {
      $('#ghv-application-type').val('membership');
      $('#ghv-voter-id').val(0);
      $('#ghv-application-title').text('RWA Membership Application');
      $('#ghv-application-intro').text('Your name was not found. Please submit details with membership fee payment proof.');
      $('#ghv-bank-section,.ghv-payment-field').show();
      $('#ghv-transaction-id,#ghv-payment-receipt').prop('required', true);
      $('#ghv-update-box').hide();
      $('#ghv-requested-update').prop('required', false).val('');
    }
  }

  $('#ghv-search-form').on('submit', function(e){
    e.preventDefault();
    var q = $('#ghv-search-query').val();
    $('#ghv-search-result').html('<div class="ghv-loading">Searching...</div>');
    $('#ghv-application-card').hide();
    setApplicationMode('membership');
    $.post(GHVRWA.ajax_url, {action:'ghv_search_voter', nonce:GHVRWA.nonce, query:q}, function(res){
      if(!res || !res.success){ $('#ghv-search-result').html('<div class="ghv-alert ghv-error">Search failed. Please try again.</div>'); return; }
      if(!res.data.found){
        $('#ghv-search-result').html('<div class="ghv-alert ghv-error">No voter record found. Please apply for membership below.</div>');
        setApplicationMode('membership');
        $('#ghv-application-card').slideDown();
        return;
      }
      window.GHVRWA_LAST_ROWS = res.data.rows || [];
      var html = '<div class="ghv-alert ghv-success">Name found in voter list.</div><div class="ghv-results">';
      res.data.rows.forEach(function(r, idx){
        html += '<div class="ghv-result-card"><h4>'+esc(r.first_owner || r.second_owner)+'</h4>'+ 
          '<p><strong>Tower:</strong> '+esc(r.tower)+' &nbsp; <strong>Flat:</strong> '+esc(r.flat)+'</p>'+ 
          '<p><strong>Second Owner:</strong> '+esc(r.second_owner)+'</p>'+ 
          '<p><strong>Membership No:</strong> '+esc(r.membership_no)+' &nbsp; <strong>Eligible Voter:</strong> '+esc(r.eligible_voter)+'</p>'+ 
          '<p><strong>Voted Earlier:</strong> '+esc(r.voted_earlier)+'</p>'+ 
          '<p><button type="button" class="ghv-secondary ghv-update-request" data-index="'+idx+'">Request correction / update details</button></p></div>';
      });
      html += '</div><p><button type="button" id="ghv-show-application" class="ghv-secondary">Apply for new membership instead</button></p>';
      $('#ghv-search-result').html(html);
    });
  });

  $(document).on('click', '#ghv-show-application', function(){ setApplicationMode('membership'); $('#ghv-application-card').slideDown(); });
  $(document).on('click', '.ghv-update-request', function(){
    var idx = parseInt($(this).attr('data-index'), 10);
    var voter = (window.GHVRWA_LAST_ROWS || [])[idx] || null;
    setApplicationMode('update', voter);
    $('#ghv-application-card').slideDown();
    document.getElementById('ghv-application-card').scrollIntoView({behavior:'smooth', block:'start'});
  });

  $('#ghv-application-form').on('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    fd.append('action','ghv_submit_application');
    fd.append('nonce',GHVRWA.nonce);
    $('#ghv-application-result').html('<div class="ghv-loading">Submitting...</div>');
    $.ajax({url:GHVRWA.ajax_url, type:'POST', data:fd, contentType:false, processData:false, success:function(res){
      if(res && res.success){ $('#ghv-application-result').html('<div class="ghv-alert ghv-success">'+esc(res.data.message)+'</div>'); $('#ghv-application-form')[0].reset(); setApplicationMode('membership'); }
      else { $('#ghv-application-result').html('<div class="ghv-alert ghv-error">'+esc((res && res.data && res.data.message) ? res.data.message : 'Submission failed')+'</div>'); }
    }, error:function(){ $('#ghv-application-result').html('<div class="ghv-alert ghv-error">Submission failed. Please try again.</div>'); }});
  });
});
