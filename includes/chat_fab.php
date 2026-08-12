<?php
// chat_fab.php — Avito-style chat v6
$cu = auth_user();
if (!$cu) return;
$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_msgs = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_notifs = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$cu['id']]);
$notifs = $stmt->fetchAll();

$stmt = $pdo->prepare("
  SELECT m.*, l.title AS listing_title, l.id AS lid,
    u.name AS other_name, u.avatar_url AS other_avatar
  FROM messages m
  JOIN listings l ON m.listing_id = l.id
  JOIN users u ON IF(m.sender_id = ?, m.receiver_id, m.sender_id) = u.id
  WHERE (m.sender_id = ? OR m.receiver_id = ?)
  GROUP BY m.listing_id, IF(m.sender_id = ?, m.receiver_id, m.sender_id)
  ORDER BY m.created_at DESC LIMIT 20
");
$stmt->execute([$cu['id'], $cu['id'], $cu['id'], $cu['id']]);
$chats = $stmt->fetchAll();

$unreadByChat = [];
foreach ($chats as $ch) {
  $other = ($ch['sender_id'] == $cu['id']) ? $ch['receiver_id'] : $ch['sender_id'];
  $key = $ch['lid'] . '_' . $other;
  if (!isset($unreadByChat[$key])) {
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE listing_id=? AND sender_id=? AND receiver_id=? AND is_read=0');
    $stmt2->execute([$ch['lid'], $other, $cu['id']]);
    $unreadByChat[$key] = (int)$stmt2->fetchColumn();
  }
}
$myId = (int)$cu['id'];
?>
<style>
.chat-fab-container{position:fixed;bottom:1.25rem;right:1.25rem;z-index:90;display:flex;flex-direction:column;gap:0.625rem}
.chat-fab-btn{width:3rem;height:3rem;border-radius:50%;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(15,23,32,0.12);transition:transform .2s,box-shadow .2s;position:relative}
.chat-fab-btn:hover{transform:scale(1.06);box-shadow:0 6px 18px rgba(15,23,32,0.18)}
.chat-fab-btn:active{transform:scale(.95)}
.chat-fab-chat{background:#121E2B;color:#fff}
.chat-fab-bell{background:#fff;color:#121E2B;border:1px solid #DFE4EA}
.chat-fab-badge{position:absolute;top:-4px;right:-4px;background:#DC2626;color:#fff;font-size:.625rem;font-weight:700;min-width:1.125rem;height:1.125rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;padding:0 .25rem}

.chat-widget{position:fixed;bottom:4.5rem;right:1.25rem;z-index:91;width:24rem;height:34rem;max-height:calc(100vh - 6rem);background:#fff;border-radius:16px;box-shadow:0 24px 60px -12px rgba(15,23,32,0.25);display:none;flex-direction:column;overflow:hidden}
.chat-widget.open{display:flex}

/* Header */
.chat-w-header{padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #EBEEF2;flex-shrink:0}
.chat-w-title{font-size:1rem;font-weight:700;color:#121E2B}
.chat-w-close{width:2rem;height:2rem;border:0;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:8px;color:#7A8A9A;transition:all .15s}
.chat-w-close:hover{background:#F0F3F7;color:#121E2B}
.chat-w-body{flex:1;overflow-y:auto}

/* Chat list */
.chat-list-item{padding:.75rem 1rem;border-bottom:1px solid #F0F3F7;cursor:pointer;transition:background .12s;display:flex;gap:.625rem;align-items:center}
.chat-list-item:hover{background:#F7F9FB}
.chat-avatar{border-radius:50%;background:#EEF2F6;display:flex;align-items:center;justify-content:center;font-weight:600;color:#7A8A9A;overflow:hidden;flex-shrink:0}
.chat-avatar.lg{width:2.75rem;height:2.75rem;font-size:.75rem}
.chat-avatar.md{width:2.25rem;height:2.25rem;font-size:.625rem}
.chat-avatar.sm{width:1.75rem;height:1.75rem;font-size:.5rem}
.chat-avatar img{width:100%;height:100%;object-fit:cover}
.chat-list-name{font-size:.875rem;font-weight:600;color:#121E2B;line-height:1.2}
.chat-list-preview{font-size:.75rem;color:#7A8A9A;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:.125rem;max-width:14rem}
.chat-list-time{font-size:.6875rem;color:#9AAAB8;white-space:nowrap}
.chat-list-unread{background:#DC2626;color:#fff;font-size:.625rem;font-weight:700;min-width:1.125rem;height:1.125rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;padding:0 .25rem;flex-shrink:0;margin-left:auto}
.chat-list-meta{font-size:.6875rem;color:#9AAAB8;margin-top:.125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Thread */
.chat-thread{display:none;flex-direction:column;height:100%}
.chat-thread.active{display:flex}
.chat-thread-hdr{display:flex;align-items:center;gap:.5rem;padding:.625rem .875rem;border-bottom:1px solid #EBEEF2;background:#fff;flex-shrink:0}
.chat-thread-hdr:hover{background:#F7F9FB}
.chat-thread-name{font-weight:700;color:#121E2B;font-size:.875rem;line-height:1.2}
.chat-thread-status{font-size:.6875rem;color:#9AAAB8}
.chat-thread-status.online{color:#16A34A}
.chat-thread-back{color:#7A8A9A;flex-shrink:0;cursor:pointer;background:none;border:0;padding:.25rem}

/* Messages — Avito style */
.chat-msgs{flex:1;overflow-y:auto;padding:.625rem .875rem;display:flex;flex-direction:column;gap:.25rem;background:#fff}
.chat-date-sep{text-align:center;font-size:.6875rem;color:#9AAAB8;margin:.5rem 0;padding:.25rem .5rem;background:#F7F9FB;border-radius:8px;align-self:center}
.chat-msg-row{display:flex;max-width:80%;flex-direction:column}
.chat-msg-row.out{position:relative;align-self:flex-end;align-items:flex-end}
.chat-msg-row.in{position:relative;align-self:flex-start;align-items:flex-start}
.chat-msg-bubble{padding:.5rem .75rem;border-radius:14px;font-size:.875rem;line-height:1.35;word-wrap:break-word;position:relative}
.chat-msg-row.out .chat-msg-bubble{background:#E8F4FB;color:#121E2B;border-bottom-right-radius:4px}
.chat-msg-row.in .chat-msg-bubble{background:#EEF2F6;color:#121E2B;border-bottom-left-radius:4px}
.chat-msg-meta{display:flex;align-items:center;gap:.25rem;margin-top:.125rem;font-size:.6875rem;color:#9AAAB8;padding:0 .25rem}
.chat-msg-row.out .chat-msg-meta{justify-content:flex-end}
/* Avito-style ticks: inside meta, after time */
.chat-tick{display:inline-block;font-size:14px;font-weight:700;line-height:1;margin-left:2px}
.chat-tick.read{color:#39B54A}
.chat-tick.unread{color:#BFC8D4}

/* Typing */
.chat-typing{padding:.25rem .875rem;font-size:.75rem;color:#7A8A9A;display:none;font-style:italic;flex-shrink:0}
.chat-typing.active{display:block}

/* Input — Avito style */
.chat-input-row{border-top:1px solid #EBEEF2;padding:.5rem .75rem;display:flex;gap:.375rem;align-items:center;background:#fff;flex-shrink:0}
.chat-input-wrap{flex:1;position:relative}
.chat-input{width:100%;border:1px solid #DFE4EA;border-radius:22px;padding:.5rem 1rem;font-size:.875rem;outline:none;transition:border-color .15s;background:#F7F9FB}
.chat-input:focus{border-color:#1B6B8A;background:#fff}
.chat-send{width:2.5rem;height:2.5rem;border:0;border-radius:50%;background:#1B6B8A;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.chat-send:hover{background:#155A75;transform:scale(1.05)}
.chat-send:disabled{background:#C8D0DA;cursor:not-allowed;transform:none}

/* Notifications */
.notif-panel{position:fixed;bottom:4.5rem;right:1.25rem;z-index:91;width:20rem;max-height:24rem;background:#fff;border-radius:16px;box-shadow:0 24px 60px -12px rgba(15,23,32,0.25);display:none;flex-direction:column;overflow:hidden}
.notif-panel.open{display:flex}
.notif-item{padding:.75rem 1rem;border-bottom:1px solid #F0F3F7;cursor:pointer;transition:background .12s}
.notif-item:hover{background:#F7F9FB}
.notif-text{font-size:.8125rem;color:#121E2B}
.notif-time{font-size:.6875rem;color:#9AAAB8;margin-top:.25rem}

/* Delete button on own messages */
.chat-msg-actions{display:none;position:absolute;top:-8px;right:-8px;z-index:2}
.chat-msg-row.out:hover .chat-msg-actions{display:block}
.chat-msg-del{width:20px;height:20px;border:0;border-radius:50%;background:#DC2626;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,0.15);transition:all .15s}
.chat-msg-del:hover{background:#B91C1C;transform:scale(1.1)}

.chat-empty{padding:2rem 1rem;text-align:center;color:#9AAAB8;font-size:.875rem}
</style>

<div class="chat-fab-container">
  <button class="chat-fab-btn chat-fab-bell" onclick="toggleNotif()" title="Уведомления">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if ($unread_notifs > 0): ?><span class="chat-fab-badge"><?=$unread_notifs?></span><?php endif; ?>
  </button>
  <button class="chat-fab-btn chat-fab-chat" onclick="toggleChat()" title="Сообщения">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <?php if ($unread_msgs > 0): ?><span class="chat-fab-badge"><?=$unread_msgs?></span><?php endif; ?>
  </button>
</div>

<div id="notifPanel" class="notif-panel">
  <?= csrf_field() ?>
  <div class="chat-w-header">
    <span class="chat-w-title">Уведомления</span>
    <div style="display:flex;align-items:center;gap:0.5rem">
      <?php if (!empty($notifs)): ?>
      <button onclick="markNotifsRead()" style="background:none;border:0;cursor:pointer;font-size:0.75rem;color:#1B6B8A;font-weight:600">Прочитать все</button>
      <?php endif; ?>
      <button class="chat-w-close" onclick="toggleNotif()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
  </div>
  <div class="chat-w-body">
    <?php if (empty($notifs)): ?><div class="chat-empty">Нет уведомлений</div>
    <?php else: foreach($notifs as $n): ?>
      <?php if ($n['link']): ?><a href="<?=h($n['link'])?>" class="notif-item" style="display:block;text-decoration:none;color:inherit"><?php else: ?><div class="notif-item"><?php endif; ?><div class="notif-text"><?=h($n['text'])?></div><div class="notif-time"><?=time_ago($n['created_at'])?></div><?php if ($n['link']): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; endif; ?>
  </div>
</div>

<div id="chatWidget" class="chat-widget">
  <!-- List view -->
  <div id="chatListView" class="chat-thread active">
    <div class="chat-w-header">
      <span class="chat-w-title">Сообщения</span>
      <button class="chat-w-close" onclick="toggleChat()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="chat-w-body">
      <?php if (empty($chats)): ?>
        <div class="chat-empty">Нет сообщений<br><br><a href="/catalog" style="color:#1B6B8A;font-weight:600">Найти объявления →</a></div>
      <?php else: foreach($chats as $ch):
        $other = ($ch['sender_id'] == $cu['id']) ? $ch['receiver_id'] : $ch['sender_id'];
        $key = $ch['lid'].'_'.$other; $unr = $unreadByChat[$key] ?? 0;
        $isMine = ($ch['sender_id'] == $cu['id']);
        $preview = ($isMine ? 'Вы: ' : '') . mb_substr($ch['text'], 0, 50);
      ?>
        <div class="chat-list-item" onclick="openThread(<?=$ch['lid']?>,<?=$other?>,'<?=h(addslashes($ch['other_name']))?>','<?=h(addslashes($ch['listing_title']))?>','<?=h(addslashes($ch['other_avatar']??''))?>')">
          <div class="chat-avatar lg"><?php if($ch['other_avatar']):?><img src="<?=h($ch['other_avatar'])?>" alt=""><?php else:?><?=mb_substr($ch['other_name'],0,2)?><?php endif;?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
              <span class="chat-list-name"><?=h($ch['other_name'])?></span>
              <span class="chat-list-time"><?=time_ago($ch['created_at'])?></span>
            </div>
            <div class="chat-list-preview"><?=h($preview)?></div>
            <div class="chat-list-meta"><?=h($ch['listing_title'])?></div>
          </div>
          <?php if($unr>0):?><span class="chat-list-unread"><?=$unr?></span><?php endif;?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Thread view -->
  <div id="chatThreadView" class="chat-thread">
    <div class="chat-thread-hdr">
      <button class="chat-thread-back" onclick="backToList()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </button>
      <div class="chat-avatar md" id="threadAvatar"></div>
      <div style="flex:1;min-width:0">
        <div class="chat-thread-name" id="threadName"></div>
        <div class="chat-thread-status" id="threadStatus"></div>
      </div>
    </div>
    <div class="chat-msgs" id="chatMessages"></div>
    <div class="chat-typing" id="chatTyping">печатает...</div>
    <div class="chat-input-row">
      <div class="chat-input-wrap">
        <input type="text" class="chat-input" id="chatInput" placeholder="Сообщение..." onkeydown="if(event.key==='Enter')sendMessage()" oninput="onTyping()">
      </div>
      <button class="chat-send" id="chatSendBtn" onclick="sendMessage()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
var currentLid=0,currentUid=0,pollTimer=null,typingTimer=null,myUid=<?=$myId?>;
var otherAvatar='',otherName='';

function toggleChat(){var w=document.getElementById('chatWidget'),n=document.getElementById('notifPanel');n.classList.remove('open');w.classList.toggle('open');if(!w.classList.contains('open')){backToList();if(pollTimer){clearInterval(pollTimer);pollTimer=null}}}
function toggleNotif(){var n=document.getElementById('notifPanel'),w=document.getElementById('chatWidget');w.classList.remove('open');n.classList.toggle('open')}
function markNotifsRead(){var csrf=document.querySelector('#notifPanel input[name="_csrf"]');var b='action=mark_notifs_read';if(csrf)b+='&_csrf='+encodeURIComponent(csrf.value);fetch('/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b}).then(function(r){return r.json()}).then(function(d){if(d.ok){var badges=document.querySelectorAll('.chat-fab-badge');badges.forEach(function(b){if(b.parentElement&&b.parentElement.classList.contains('chat-fab-bell'))b.remove()});document.getElementById('notifPanel').querySelector('.chat-w-body').innerHTML='<div class="chat-empty">Нет уведомлений</div>';var btn=document.querySelector('.notif-panel .chat-w-header button[onclick*="markNotifsRead"]');if(btn)btn.style.display='none'}}).catch(function(){location.reload()})}

function openThread(lid,uid,name,listing,avatar){
 currentLid=lid;currentUid=uid;otherName=name;otherAvatar=avatar;
 document.getElementById('threadName').textContent=name;
 document.getElementById('threadStatus').textContent='';
 var av=document.getElementById('threadAvatar');
 if(avatar){av.innerHTML='<img src="'+escapeHtml(avatar)+'" alt="">'}else{av.innerHTML=name.substring(0,2)}
 document.getElementById('chatListView').classList.remove('active');
 document.getElementById('chatThreadView').classList.add('active');
 document.getElementById('chatMessages').innerHTML='<div class="chat-empty">Загрузка...</div>';
 loadMessages();
 if(pollTimer)clearInterval(pollTimer);
 pollTimer=setInterval(loadMessages,4000);
}

function backToList(){
 document.getElementById('chatThreadView').classList.remove('active');
 document.getElementById('chatListView').classList.add('active');
 if(pollTimer){clearInterval(pollTimer);pollTimer=null}
 currentLid=0;currentUid=0;
}

function loadMessages(){
 if(!currentLid||!currentUid)return;
 fetch('/api/messages?lid='+currentLid+'&uid='+currentUid+'&_='+Date.now())
 .then(function(r){return r.json()})
 .then(function(data){
  var box=document.getElementById('chatMessages');
  var st=document.getElementById('threadStatus');
  if(data.other&&data.other.last_seen){
   var ls=new Date(data.other.last_seen.replace(/-/g,'/'));
   var diff=Math.floor((new Date()-ls)/1000);
   if(diff<120){st.className='chat-thread-status online';st.textContent='в сети'}
   else{st.className='chat-thread-status';
     if(diff<3600)st.textContent='был(а) '+Math.floor(diff/60)+' мин. назад';
     else if(diff<86400)st.textContent='был(а) '+Math.floor(diff/3600)+' ч. назад';
     else st.textContent='был(а) '+ls.toLocaleDateString('ru-RU');
   }
  }
  document.getElementById('chatTyping').className='chat-typing'+(data.typing?' active':'');
  if(!data.messages||data.messages.length===0){box.innerHTML='<div class="chat-empty">Напишите первое сообщение</div>';return}
  var html='',lastDate='';
  for(var i=0;i<data.messages.length;i++){
   var m=data.messages[i];
   var d=new Date(m.created_at.replace(/-/g,'/'));
   var dateStr=d.toLocaleDateString('ru-RU',{day:'numeric',month:'long'});
   if(dateStr!==lastDate){html+='<div class="chat-date-sep">'+dateStr+'</div>';lastDate=dateStr}
   var mine=(parseInt(m.sender_id)===myUid);
   var time=d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});
   /* Avito-style: tick inside meta, after time */
   var tick='';
   if(mine){
     var read=(m.is_read==1||m.is_read==='1'||m.is_read===true||parseInt(m.is_read)===1);
     tick='<span class="chat-tick '+(read?'read':'unread')+'">'+(read?'\u2713\u2713':'\u2713')+'</span>';
   }
   html+='<div class="chat-msg-row '+(mine?'out':'in')+'">';
   if(mine) html+='<div class="chat-msg-actions"><button class="chat-msg-del" onclick="deleteMessage('+m.id+')" title="Удалить">&times;</button></div>';
   html+='<div class="chat-msg-bubble">'+escapeHtml(m.text)+'</div>';
   html+='<div class="chat-msg-meta"><span>'+time+'</span>'+tick+'</div>';
   html+='</div>';
  }
  box.innerHTML=html;
  box.scrollTop=box.scrollHeight;
 }).catch(function(){});
}

function onTyping(){
 if(!currentLid||!currentUid)return;
 if(typingTimer)clearTimeout(typingTimer);
 typingTimer=setTimeout(function(){},500);
 fetch('/api/typing?_='+Date.now(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+currentLid}).catch(function(){});
}

function sendMessage(){
 var input=document.getElementById('chatInput'),text=input.value.trim();
 if(!text||!currentLid)return;
 input.value='';input.disabled=true;
 fetch('/api/send?_='+Date.now(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+currentLid+'&uid='+currentUid+'&text='+encodeURIComponent(text)})
 .then(function(r){return r.json()})
 .then(function(data){input.disabled=false;input.focus();if(data.ok)loadMessages();else alert('Ошибка отправки')})
 .catch(function(){input.disabled=false});
}

function escapeHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

function deleteMessage(mid){
 if(!confirm('Удалить сообщение?'))return;
 fetch('/api/delete?_='+Date.now(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mid='+mid})
 .then(function(r){return r.json()})
 .then(function(d){if(d.ok)loadMessages()})
 .catch(function(){});
}

document.addEventListener('click',function(e){
 if(!e.target.closest('.chat-fab-container')&&!e.target.closest('.chat-widget')&&!e.target.closest('.notif-panel')){
  document.getElementById('chatWidget').classList.remove('open');
  document.getElementById('notifPanel').classList.remove('open');
  if(document.getElementById('chatThreadView').classList.contains('active'))backToList();
 }
});
</script>
