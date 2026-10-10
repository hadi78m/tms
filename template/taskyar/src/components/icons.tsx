import type { SVGProps } from "react";

type P = SVGProps<SVGSVGElement>;

const S = ({ children, className = "h-5 w-5", ...rest }: P & { children: React.ReactNode }) => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth={1.7}
    strokeLinecap="round"
    strokeLinejoin="round"
    className={className}
    aria-hidden="true"
    {...rest}
  >
    {children}
  </svg>
);

export const IconGauge = (p: P) => (
  <S {...p}>
    <path d="M3.6 17a9 9 0 1 1 16.8 0" />
    <path d="M12 13.5 16 9" />
    <circle cx="12" cy="14.4" r="1.6" />
    <path d="M3.6 17h3M17.4 17h3" />
  </S>
);

export const IconBoard = (p: P) => (
  <S {...p}>
    <rect x="3" y="4" width="5.2" height="16" rx="1.6" />
    <rect x="9.4" y="4" width="5.2" height="11" rx="1.6" />
    <rect x="15.8" y="4" width="5.2" height="14" rx="1.6" />
  </S>
);

export const IconChecklist = (p: P) => (
  <S {...p}>
    <path d="M4 6.5 5.6 8 8.4 5" />
    <path d="M4 13l1.6 1.5L8.4 11.5" />
    <path d="m4 19.4 1.6 1.4L8.4 18" />
    <path d="M11.5 7h8M11.5 13.5h8M11.5 20h5.5" />
  </S>
);

export const IconGantt = (p: P) => (
  <S {...p}>
    <path d="M3 4.5v15" />
    <rect x="6" y="5" width="9" height="3.4" rx="1.4" />
    <rect x="9" y="10.3" width="11" height="3.4" rx="1.4" />
    <rect x="6" y="15.6" width="6" height="3.4" rx="1.4" />
  </S>
);

export const IconSearch = (p: P) => (
  <S {...p}>
    <circle cx="10.8" cy="10.8" r="6.3" />
    <path d="m15.6 15.6 4 4" />
  </S>
);

export const IconBell = (p: P) => (
  <S {...p}>
    <path d="M6.4 10a5.6 5.6 0 0 1 11.2 0c0 3.2.7 4.8 1.6 5.9.5.6 0 1.6-.8 1.6H5.6c-.8 0-1.3-1-.8-1.6C5.7 14.8 6.4 13.2 6.4 10Z" />
    <path d="M10 20.4a2.4 2.4 0 0 0 4 0" />
  </S>
);

export const IconPlus = (p: P) => (
  <S {...p}>
    <path d="M12 5v14M5 12h14" />
  </S>
);

export const IconChevron = (p: P) => (
  <S {...p}>
    <path d="m6 9 6 6 6-6" />
  </S>
);

export const IconArrowLeft = (p: P) => (
  <S {...p}>
    <path d="M19 12H5M11 6l-6 6 6 6" />
  </S>
);

export const IconClock = (p: P) => (
  <S {...p}>
    <circle cx="12" cy="12" r="8.4" />
    <path d="M12 7.6V12l3 1.9" />
  </S>
);

export const IconCalendar = (p: P) => (
  <S {...p}>
    <rect x="3.4" y="5" width="17.2" height="15.4" rx="2.4" />
    <path d="M3.4 9.8h17.2M8.4 3.4v3.2M15.6 3.4v3.2" />
    <path d="M7.6 13.4h2M11 13.4h2M14.4 13.4h2M7.6 16.8h2M11 16.8h2" />
  </S>
);

export const IconFlag = (p: P) => (
  <S {...p}>
    <path d="M5.5 21V3.6" />
    <path d="M5.5 4.6h10.9l-1.9 3.9 1.9 3.9H5.5z" />
  </S>
);

export const IconLink = (p: P) => (
  <S {...p}>
    <path d="M9.6 14.4a3.8 3.8 0 0 0 5.6.3l2.6-2.6a3.9 3.9 0 0 0-5.5-5.5l-1.4 1.4" />
    <path d="M14.4 9.6a3.8 3.8 0 0 0-5.6-.3L6.2 11.9a3.9 3.9 0 0 0 5.5 5.5l1.4-1.4" />
  </S>
);

export const IconImage = (p: P) => (
  <S {...p}>
    <rect x="3.4" y="4.6" width="17.2" height="14.8" rx="2.4" />
    <circle cx="9" cy="10" r="1.7" />
    <path d="m4.4 17.4 4.5-4.2a2 2 0 0 1 2.7 0l5.6 5.2" />
    <path d="m14.6 14.2 1.5-1.3a2 2 0 0 1 2.6 0l1.3 1.1" />
  </S>
);

export const IconClip = (p: P) => (
  <S {...p}>
    <path d="M17.4 10.6 11.7 16.3a3.6 3.6 0 0 1-5.1-5.1l6.6-6.6a2.5 2.5 0 0 1 3.6 3.6l-6.6 6.6a1.5 1.5 0 0 1-2.1-2.1l5.8-5.8" />
  </S>
);

export const IconTrash = (p: P) => (
  <S {...p}>
    <path d="M4.6 6.8h14.8M9.4 6.8V4.9a1.4 1.4 0 0 1 1.4-1.4h2.4a1.4 1.4 0 0 1 1.4 1.4v1.9" />
    <path d="M6.6 6.8 7.5 19a1.7 1.7 0 0 0 1.7 1.6h5.6a1.7 1.7 0 0 0 1.7-1.6l.9-12.2" />
    <path d="M10.4 10.4v6.4M13.6 10.4v6.4" />
  </S>
);

export const IconPencil = (p: P) => (
  <S {...p}>
    <path d="M16.4 3.9a2 2 0 0 1 2.9 2.8L8.6 17.4l-4 1.2 1.2-4z" />
    <path d="m14.6 5.7 3.2 3.2" />
  </S>
);

export const IconFilter = (p: P) => (
  <S {...p}>
    <path d="M3.8 5.4h16.4l-6.4 7.6v5.6l-3.6 1.8v-7.4z" />
  </S>
);

export const IconCheck = (p: P) => (
  <S {...p}>
    <path d="m4.8 12.6 4.6 4.6L19.2 7.4" />
  </S>
);

export const IconX = (p: P) => (
  <S {...p}>
    <path d="M6 6l12 12M18 6 6 18" />
  </S>
);

export const IconMenu = (p: P) => (
  <S {...p}>
    <path d="M4 7h16M4 12h16M4 17h10" />
  </S>
);

export const IconShield = (p: P) => (
  <S {...p}>
    <path d="M12 3.2 5 6v5.6c0 4.2 2.9 7.4 7 9.2 4.1-1.8 7-5 7-9.2V6z" />
    <path d="m9.2 12 2 2 3.6-3.9" />
  </S>
);

export const IconFlame = (p: P) => (
  <S {...p}>
    <path d="M12 3.2s5.2 3.6 5.2 8.4A5.2 5.2 0 0 1 12 20.8a5.2 5.2 0 0 1-5.2-9.2c1.3-1.6 2.4-2 2.4-2s-.2 2.2 1.3 2.6c1.6-2.4 1.5-6.6 1.5-9Z" />
  </S>
);

export const IconAlert = (p: P) => (
  <S {...p}>
    <path d="M12 4.2 2.8 20h18.4z" />
    <path d="M12 10v4.2M12 17.2h.01" />
  </S>
);

export const IconUsers = (p: P) => (
  <S {...p}>
    <circle cx="9.2" cy="8.4" r="3.4" />
    <path d="M3.4 19.4a5.9 5.9 0 0 1 11.6 0" />
    <path d="M16 5.4a3.4 3.4 0 0 1 .3 6.6M17.4 14.4a5.9 5.9 0 0 1 3.2 4.4" />
  </S>
);

export const IconFolder = (p: P) => (
  <S {...p}>
    <path d="M3.4 7.2A2.2 2.2 0 0 1 5.6 5h3.6l2 2.4h7.2A2.2 2.2 0 0 1 20.6 9.6v7.8a2.2 2.2 0 0 1-2.2 2.2H5.6a2.2 2.2 0 0 1-2.2-2.2z" />
  </S>
);

export const IconSticky = (p: P) => (
  <S {...p}>
    <path d="M4.6 4.6h14.8v9.6l-5.2 5.2H4.6z" />
    <path d="M19.4 14.2h-5.2v5.2" />
    <path d="M8 8.6h8M8 11.8h5" />
  </S>
);

export const IconSettings = (p: P) => (
  <S {...p}>
    <circle cx="12" cy="12" r="3" />
    <path d="M19.6 14.2a1.5 1.5 0 0 0 .3 1.7l.1.1a1.8 1.8 0 1 1-2.6 2.6l-.1-.1a1.5 1.5 0 0 0-2.6 1.1v.2a1.8 1.8 0 1 1-3.6 0v-.1a1.5 1.5 0 0 0-2.7-1.1l-.1.1a1.8 1.8 0 1 1-2.6-2.6l.1-.1a1.5 1.5 0 0 0-1.1-2.6h-.2a1.8 1.8 0 1 1 0-3.6h.1a1.5 1.5 0 0 0 1.1-2.7l-.1-.1a1.8 1.8 0 1 1 2.6-2.6l.1.1a1.5 1.5 0 0 0 2.6-1.1v-.2a1.8 1.8 0 1 1 3.6 0v.1a1.5 1.5 0 0 0 2.7 1.1l.1-.1a1.8 1.8 0 1 1 2.6 2.6l-.1.1a1.5 1.5 0 0 0 1.1 2.6h.2a1.8 1.8 0 1 1 0 3.6h-.1a1.5 1.5 0 0 0-1.4 1z" />
  </S>
);

export const IconDownload = (p: P) => (
  <S {...p}>
    <path d="M12 3.8v10.6M8 10.8l4 3.8 4-3.8" />
    <path d="M4.4 17.2v1.6a2 2 0 0 0 2 2h11.2a2 2 0 0 0 2-2v-1.6" />
  </S>
);

export const IconDrag = (p: P) => (
  <S {...p} strokeWidth={2.2}>
    <path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" />
  </S>
);

export const IconSpark = (p: P) => (
  <S {...p}>
    <path d="M12 3.4 13.8 9 19.4 10.8 13.8 12.6 12 18.2 10.2 12.6 4.6 10.8 10.2 9z" />
    <path d="M18.4 16.4l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z" />
  </S>
);

export const IconLogout = (p: P) => (
  <S {...p}>
    <path d="M14.6 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6.6a2 2 0 0 0 2-2v-2" />
    <path d="M9.6 12h11M17.4 8.6 20.8 12l-3.4 3.4" />
  </S>
);

export const IconLayers = (p: P) => (
  <S {...p}>
    <path d="m12 3.4 8.4 4.4L12 12.2 3.6 7.8z" />
    <path d="m3.6 12.4 8.4 4.4 8.4-4.4" />
    <path d="m3.6 16.6 8.4 4.4 8.4-4.4" />
  </S>
);

export const IconTrend = (p: P) => (
  <S {...p}>
    <path d="M3.6 17.4 9 11.8l3.6 3.4 7.4-8" />
    <path d="M15.4 7.2h4.8v4.6" />
  </S>
);
