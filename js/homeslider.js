
    (function(){
      const slider = document.getElementById("verticalSlider");
      const track = document.getElementById("vsTrack");
      const dotsBox = document.getElementById("vsDots");
      const counter = document.getElementById("vsCounter");
      const topIndicator = document.getElementById("scrollTop");
      const bottomIndicator = document.getElementById("scrollBottom");

      if(!slider || !track) return;

      const slides = track.querySelectorAll(".vs-slide");
      const total = slides.length;
      let current = 0;
      let animating = false;
      let wheelDelta = 0;
      let wheelTimer = null;
      const threshold = 45;

      // CREATE DOTS
      for(let i=0; i<total; i++){
        let dot = document.createElement("span");
        dot.className = "dot";
        if(i===0) dot.classList.add("active");
        dot.onclick = function(){
          if(!animating) goToSlide(i);
        };
        dotsBox.appendChild(dot);
      }

      function updateUI(){
        document.querySelectorAll("#vsDots .dot").forEach((dot,i)=>{
          dot.classList.toggle("active", i===current);
        });
        counter.innerHTML = String(current+1).padStart(2,"0") + " / " + String(total).padStart(2,"0");
        if(topIndicator){
          topIndicator.classList.toggle("visible", current>0);
        }
        if(bottomIndicator){
          bottomIndicator.classList.toggle("visible", current<total-1);
        }
      }

      function goToSlide(index){
        if(animating) return;
        current = index;
        animating = true;
        track.style.transform = `translateY(-${current * 100}vh)`;
        updateUI();
        setTimeout(()=>{
          animating = false;
        }, 700);
      }

      // Desktop Wheel - FIXED: No visibility check, just check if slider is in viewport
      slider.addEventListener("wheel", function(e){
        let direction = e.deltaY;
        
        // Check if slider is in viewport (not necessarily fully visible)
        let rect = slider.getBoundingClientRect();
        let isInViewport = rect.top < window.innerHeight && rect.bottom > 0;
        
        // If slider is not in viewport, let page scroll normally
        if(!isInViewport) return;

        // AT BOUNDARIES: pass scroll to page
        if((current === 0 && direction < 0) || (current === total - 1 && direction > 0)){
          // Let the page scroll naturally by scrolling the window
          window.scrollBy({
            top: direction,
            behavior: "auto"
          });
          return;
        }

        // Otherwise prevent default and handle slider
        e.preventDefault();

        wheelDelta += direction;
        clearTimeout(wheelTimer);
        wheelTimer = setTimeout(()=>{
          wheelDelta = 0;
        }, 150);

        if(Math.abs(wheelDelta) >= threshold && !animating){
          if(wheelDelta > 0 && current < total - 1){
            goToSlide(current + 1);
          } else if(wheelDelta < 0 && current > 0){
            goToSlide(current - 1);
          }
          wheelDelta = 0;
        }
      }, { passive: false });

      // TOUCH SUPPORT
      let touchStart = 0;
      slider.addEventListener("touchstart", function(e){
        touchStart = e.touches[0].clientY;
      }, { passive: true });

      slider.addEventListener("touchend", function(e){
        let end = e.changedTouches[0].clientY;
        let diff = touchStart - end;
        if(Math.abs(diff) < 50) return;
        if(diff > 0 && current < total - 1){
          goToSlide(current + 1);
        }
        if(diff < 0 && current > 0){
          goToSlide(current - 1);
        }
      }, { passive: true });

      // KEYBOARD
      document.addEventListener("keydown", function(e){
        let rect = slider.getBoundingClientRect();
        let isInViewport = rect.top < window.innerHeight && rect.bottom > 0;
        if(!isInViewport) return;
        if(e.key === "ArrowDown" || e.key === "ArrowRight"){
          e.preventDefault();
          if(current < total - 1) goToSlide(current + 1);
        }
        if(e.key === "ArrowUp" || e.key === "ArrowLeft"){
          e.preventDefault();
          if(current > 0) goToSlide(current - 1);
        }
      });

      // INITIAL
      track.style.transform = "translateY(0)";
      updateUI();

    })();