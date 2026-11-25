export interface UsuarioData {
  id: number;
  username: string;
  nombre: string;
  apellido: string;
  rol: string;
  correo?: string | null;
  estado?: boolean;
}

export class Usuario implements UsuarioData {
  id: number;
  username: string;
  nombre: string;
  apellido: string;
  rol: string;
  correo: string | null;
  estado: boolean;

  constructor(data: UsuarioData) {
    this.id = Number(data.id) || 0;
    this.username = String(data.username ?? '');
    this.nombre = String(data.nombre ?? '');
    this.apellido = String(data.apellido ?? '');
    this.rol = String(data.rol ?? '');
    this.correo = data.correo ? String(data.correo) : null;
    this.estado = data.estado !== false;
  }

  esAdmin(): boolean {
    return this.rol.toLowerCase() === 'admin';
  }

  nombreCompleto(): string {
    return `${this.nombre} ${this.apellido}`.trim();
  }

  toJSON(): UsuarioData {
    return {
      id: this.id,
      username: this.username,
      nombre: this.nombre,
      apellido: this.apellido,
      rol: this.rol,
      correo: this.correo,
      estado: this.estado,
    };
  }

  static fromJSON(data: UsuarioData): Usuario {
    return new Usuario(data);
  }
}
