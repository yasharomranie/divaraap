import { defineBehavior } from '../core/behavior';

export const advertisingVideo = defineBehavior<HTMLVideoElement>({
    name: 'advertising-video',
    selector: 'video[data-ad-video]',
    mount(video) {
        const source = video.querySelector('source');
        const mobilePoster = video.dataset['mobilePoster'];
        if (mobilePoster && matchMedia('(max-width: 1023px)').matches) video.poster = mobilePoster;
        const pause = (): void => { if (!video.paused) video.pause(); };
        const visibility = (): void => { if (document.visibilityState !== 'visible') pause(); };
        const play = (): void => {
            if (document.visibilityState !== 'visible') {
                pause();
                return;
            }
            document.querySelectorAll<HTMLVideoElement>('video[data-ad-video]').forEach(other => {
                if (other !== video && !other.paused) other.pause();
            });
        };
        const error = (): void => {
            pause();
            const fallback = video.parentElement?.querySelector<HTMLElement>('[data-ad-video-fallback]');
            if (fallback) {
                fallback.hidden = false;
                video.hidden = true;
            }
        };
        const observer = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
            if (entries.some(entry => !entry.isIntersecting || entry.intersectionRatio < 0.25)) pause();
        }, { threshold: [0, 0.25] }) : undefined;
        const cleanup = (): void => {
            pause();
            observer?.disconnect();
            video.removeEventListener('play', play);
            video.removeEventListener('error', error);
            source?.removeEventListener('error', error);
            document.removeEventListener('visibilitychange', visibility);
            document.removeEventListener('livewire:navigating', cleanup);
        };
        observer?.observe(video);
        video.addEventListener('play', play);
        video.addEventListener('error', error);
        source?.addEventListener('error', error);
        document.addEventListener('visibilitychange', visibility);
        document.addEventListener('livewire:navigating', cleanup);
    },
});
