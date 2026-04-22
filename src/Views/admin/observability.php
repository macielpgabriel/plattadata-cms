<script>
    // Lazy Loading para cards de dados adicionais
    function loadLazyCards() {
        const lazyCards = document.querySelectorAll('.lazy-card');
        
        lazyCards.forEach(card => {
            const url = card.dataset.url;
            if (!url) return;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        card.innerHTML = data.html || '<div class="alert alert-info">Sem dados disponíveis.</div>';
                    } else {
                        card.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados.</div>';
                    }
                })
                .catch(() => {
                    card.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados.</div>';
                });
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const lazySection = document.getElementById('sec-lazy');
        if (lazySection) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        loadLazyCards();
                        observer.disconnect();
                    }
                });
            }, { rootMargin: '100px' });
            
            observer.observe(lazySection);
        }
    });
</script>