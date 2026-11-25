import type { SessionState } from '../session';

declare global {
  namespace Express {
    interface Request {
      sessionState: SessionState;
    }
  }
}

export {};
